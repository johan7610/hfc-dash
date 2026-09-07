<?php

namespace App\Services\RentalApplications;

use App\Models\Document;
use App\Models\RentalApplicationDocumentHighlight;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * AT-392 Phase 2 — persistent, non-destructive highlight marks for a rental-
 * application document. Deliberately mirrors App\Services\ViewingPack\
 * ViewingPackRedactionService's structure (rasterize source → GD → reassemble
 * a flattened image-only PDF via dompdf, stable artifact path, re-apply
 * overwrites) rather than a Chrome-native viewer or a second invented
 * pipeline — that service is CoreX's proven "own the render, persist marks,
 * play them back to the next viewer" implementation.
 *
 * The one deliberate divergence: redaction burns OPAQUE BLACK and destroys
 * the text layer (a POPIA requirement — nothing to preserve). A highlight is
 * the opposite by definition — translucent colour, non-destructive to the
 * agent's own eventual re-edit — so this is its own service rather than a
 * mode flag bolted onto the compliance-critical redaction code path.
 *
 * A NEW dedicated service (not a shared library) so
 * ViewingPackRedactionService — live compliance code for an unrelated
 * feature — is never touched by this screen.
 */
class RentalApplicationDocumentHighlightService
{
    private const DPI = 150;

    public const COLORS = [
        'yellow' => [255, 235, 59],
        'green'  => [76, 217, 100],
        'pink'   => [255, 105, 180],
        'blue'   => [90, 200, 250],
    ];

    private const DEFAULT_COLOR = 'yellow';

    /** Alpha 0 (opaque) – 127 (fully transparent), GD scale. ~35% opacity, like a real marker. */
    private const ALPHA = 82;

    /**
     * Rasterized source pages for the on-screen tool, plus any marks already
     * saved for this document (so reopening the tool shows the agent their
     * own existing highlights, not a blank slate).
     *
     * @return array{pages: array<int, array{index:int,width:int,height:int,data_uri:string}>, marks: array}
     */
    public function pagePreviews(Document $document): array
    {
        $pages = $this->renderSourcePages($document);

        $out = [];
        try {
            foreach ($pages as $i => $img) {
                ob_start();
                imagepng($img);
                $bytes = (string) ob_get_clean();
                $out[] = [
                    'index'    => $i,
                    'width'    => imagesx($img),
                    'height'   => imagesy($img),
                    'data_uri' => 'data:image/png;base64,' . base64_encode($bytes),
                ];
            }
        } finally {
            foreach ($pages as $img) {
                @imagedestroy($img);
            }
        }

        $existing = RentalApplicationDocumentHighlight::where('document_id', $document->id)->first();

        return [
            'pages' => $out,
            'marks' => $existing->marks_json ?? [],
        ];
    }

    /**
     * Burn the current mark set and persist a flattened, highlighted copy.
     * Idempotent — always re-renders from the pristine SOURCE (never from a
     * previously-marked copy), so removing a mark and re-applying genuinely
     * removes it, the same guarantee ViewingPackRedactionService gives
     * redaction. An empty mark set clears the highlighted copy entirely —
     * the next viewer then sees the plain original, not a needless artifact.
     *
     * @param  array<int, array<int, array{x:mixed,y:mixed,w:mixed,h:mixed,color?:string}>>  $marksByPage  page-index (0-based) => list of marks, RASTER pixel coords.
     */
    public function applyMarks(Document $document, int $agencyId, ?int $userId, array $marksByPage): RentalApplicationDocumentHighlight
    {
        $flatCount = 0;
        foreach ($marksByPage as $marks) {
            $flatCount += is_array($marks) ? count($marks) : 0;
        }

        $highlight = RentalApplicationDocumentHighlight::firstOrNew(['document_id' => $document->id]);
        $highlight->agency_id = $agencyId;
        $highlight->updated_by_user_id = $userId;
        $highlight->marks_json = $this->normalizeForStorage($marksByPage);

        if ($flatCount === 0) {
            $this->deleteArtifactIfAny($highlight->highlighted_file_path);
            $highlight->highlighted_file_path = null;
            $highlight->save();

            return $highlight;
        }

        $pages = $this->renderSourcePages($document);

        try {
            foreach ($pages as $i => $img) {
                $marks = $marksByPage[$i] ?? $marksByPage[(string) $i] ?? [];
                if (! is_array($marks)) {
                    continue;
                }

                imagealphablending($img, true);
                foreach ($marks as $m) {
                    $x = (int) round((float) ($m['x'] ?? 0));
                    $y = (int) round((float) ($m['y'] ?? 0));
                    $w = (int) round((float) ($m['w'] ?? 0));
                    $h = (int) round((float) ($m['h'] ?? 0));
                    if ($w <= 0 || $h <= 0) {
                        continue;
                    }
                    $rgb = self::COLORS[$m['color'] ?? self::DEFAULT_COLOR] ?? self::COLORS[self::DEFAULT_COLOR];
                    $fill = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], self::ALPHA);
                    imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $fill);
                }
            }

            $pdfBytes = $this->assemblePdf($pages);
        } finally {
            foreach ($pages as $img) {
                @imagedestroy($img);
            }
        }

        $rel = 'rental-applications/document-highlights/doc-' . $document->id . '.pdf';
        Storage::disk('local')->put($rel, $pdfBytes);

        $highlight->highlighted_file_path = $rel;
        $highlight->save();

        return $highlight;
    }

    /** Keep only well-shaped mark data in storage — never trust the raw request payload verbatim into a JSON column. */
    private function normalizeForStorage(array $marksByPage): array
    {
        $out = [];
        foreach ($marksByPage as $page => $marks) {
            if (! is_array($marks)) {
                continue;
            }
            $pageIndex = (int) $page;
            foreach ($marks as $m) {
                $w = (float) ($m['w'] ?? 0);
                $h = (float) ($m['h'] ?? 0);
                if ($w <= 0 || $h <= 0) {
                    continue;
                }
                $out[$pageIndex][] = [
                    'x' => (float) ($m['x'] ?? 0),
                    'y' => (float) ($m['y'] ?? 0),
                    'w' => $w,
                    'h' => $h,
                    'color' => array_key_exists($m['color'] ?? null, self::COLORS) ? $m['color'] : self::DEFAULT_COLOR,
                ];
            }
        }

        return $out;
    }

    private function deleteArtifactIfAny(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array<int, \GdImage>
     *
     * AT-173 — every byte-reader of a Document MUST go through
     * decryptedContents(), never read the raw storage file directly (some
     * documents, e.g. FICA, are enveloped/encrypted at rest; this call
     * transparently decrypts, or passes plaintext through unchanged for
     * everything else). Matches viewDocumentInline() in this same
     * controller. pdftoppm needs a real file path, so the decrypted bytes
     * are written to a throwaway temp file, used, then deleted — the
     * decrypted plaintext never touches permanent storage.
     */
    private function renderSourcePages(Document $doc): array
    {
        $bytes = $doc->decryptedContents();
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Source document could not be read.');
        }

        $isPdf = str_contains(strtolower((string) $doc->mime_type), 'pdf');

        if (! $isPdf) {
            $img = @imagecreatefromstring($bytes);
            if (! $img) {
                throw new \RuntimeException('Source image could not be read.');
            }

            return [$img];
        }

        $tmpFile = sys_get_temp_dir() . '/rental_highlight_src_' . uniqid('', true) . '.pdf';
        file_put_contents($tmpFile, $bytes);

        try {
            return $this->rasterizePdf($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    /** @return array<int, \GdImage> */
    private function rasterizePdf(string $pdfPath): array
    {
        $count = $this->pageCount($pdfPath);
        if ($count < 1) {
            throw new \RuntimeException('Could not determine the PDF page count.');
        }

        $tmpDir = sys_get_temp_dir() . '/rental_highlight_' . uniqid('', true);
        @mkdir($tmpDir, 0755, true);

        $pdftoppm = config('splitter.pdftoppm_path', 'pdftoppm');
        $images   = [];

        try {
            for ($page = 1; $page <= $count; $page++) {
                $prefix = $tmpDir . '/page';
                $proc = new Process([
                    $pdftoppm,
                    '-f', (string) $page,
                    '-l', (string) $page,
                    '-png',
                    '-r', (string) self::DPI,
                    $pdfPath,
                    $prefix,
                ]);
                $proc->setTimeout(120);
                $proc->run();

                if (! $proc->isSuccessful()) {
                    throw new \RuntimeException('pdftoppm failed: ' . trim($proc->getErrorOutput()));
                }

                $files = glob($prefix . '-*.png');
                if (empty($files)) {
                    throw new \RuntimeException('pdftoppm produced no output for page ' . $page . '.');
                }
                sort($files);
                $img = @imagecreatefrompng($files[0]);
                foreach ($files as $f) {
                    @unlink($f);
                }
                if (! $img) {
                    throw new \RuntimeException('Rasterized page ' . $page . ' was unreadable.');
                }
                $images[] = $img;
            }
        } catch (\Throwable $e) {
            foreach ($images as $img) {
                @imagedestroy($img);
            }
            $this->cleanupDir($tmpDir);
            throw $e;
        }

        $this->cleanupDir($tmpDir);

        return $images;
    }

    private function pageCount(string $pdfPath): int
    {
        $proc = new Process(['pdfinfo', $pdfPath]);
        $proc->setTimeout(30);
        $proc->run();

        if ($proc->isSuccessful() && preg_match('/Pages:\s+(\d+)/', $proc->getOutput(), $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /** @param  array<int, \GdImage>  $pages */
    private function assemblePdf(array $pages): string
    {
        $first = $pages[0];
        $wPt = imagesx($first) * 72 / self::DPI;
        $hPt = imagesy($first) * 72 / self::DPI;

        $body = '';
        $last = count($pages) - 1;
        foreach ($pages as $idx => $img) {
            ob_start();
            imagejpeg($img, null, 90);
            $bytes = (string) ob_get_clean();
            $uri   = 'data:image/jpeg;base64,' . base64_encode($bytes);
            $break = $idx < $last ? 'page-break-after:always;' : '';
            $body .= '<div style="' . $break . '"><img src="' . $uri . '" style="width:100%;display:block;"></div>';
        }

        $html = '<!doctype html><html><head><style>'
            . '@page{margin:0;}html,body{margin:0;padding:0;}img{margin:0;border:0;}'
            . '</style></head><body>' . $body . '</body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper([0, 0, $wPt, $hPt]);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('isPhpEnabled', false);
        $pdf->setOption('dpi', 96);

        $fontDir = $this->fontCacheDir();
        if ($fontDir !== null) {
            $pdf->setOption('fontDir', $fontDir);
            $pdf->setOption('fontCache', $fontDir);
        }

        return (string) $pdf->output();
    }

    private function fontCacheDir(): ?string
    {
        $dir = storage_path('app/dompdf-fonts');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_dir($dir) && is_writable($dir) ? $dir : null;
    }

    private function cleanupDir(string $dir): void
    {
        foreach ((array) glob($dir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
