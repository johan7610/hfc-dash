<?php

namespace App\Services\RentalApplications;

use App\Models\Document;
use App\Models\RentalApplicationDocumentHighlight;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * AT-392 Phase 2 — persistent, non-destructive marks (highlight strokes and
 * point notes) for a rental-application document. Deliberately mirrors
 * App\Services\ViewingPack\ViewingPackRedactionService's structure (rasterize
 * source → GD → reassemble a flattened image-only PDF via dompdf, stable
 * artifact path, re-apply overwrites) rather than a Chrome-native viewer or a
 * second invented pipeline — that service is CoreX's proven "own the render,
 * persist marks, play them back to the next viewer" implementation.
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
 *
 * Mark shapes, unified in one array (marks_json), page-index keyed:
 *   {type: 'highlight', points: [{x,y}, ...], width: int, color: string}
 *   {type: 'note', x: float, y: float, text: string, color: string}
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

    /** Highlighter stroke thickness in RASTER px at DPI above. */
    private const STROKE_WIDTH = 26;

    /**
     * Rasterized source pages for the on-screen tool, plus any marks already
     * saved for this document (so reopening the tool shows the agent their
     * own existing marks, not a blank slate).
     *
     * Performance, 2026-09-08 — measured against a real 17-page/928KB
     * document: pagePreviews() took 11.5s end-to-end (~676ms/page). Broke it
     * down: pdftoppm rendering itself is ~512ms/page (the genuine, largely
     * irreducible cost — same DPI the proven redaction tool already uses),
     * but ~120ms/page (18% of total) was pure waste — decoding the just-
     * written PNG through GD (imagecreatefrompng) and re-encoding it
     * (imagepng) for NO reason, since a passive preview never touches a
     * pixel. Fixed here: read the rasterized PNG bytes straight off disk for
     * previewing — no GD round-trip unless a burn is actually happening.
     * Second fix: rasterized pages are now CACHED on disk per document (see
     * cachedPagePaths()) — a document reopened by the same or another agent
     * skips pdftoppm entirely on every subsequent open, cutting the dominant
     * ~8.7s cost to near zero after the first view.
     *
     * @return array{pages: array<int, array{index:int,width:int,height:int,data_uri:string}>, marks: array}
     */
    public function pagePreviews(Document $document): array
    {
        $pagePaths = $this->cachedOrRasterizedPagePaths($document);

        $out = [];
        foreach ($pagePaths as $i => $path) {
            [$w, $h] = getimagesize($path);
            $out[] = [
                'index'    => $i,
                'width'    => $w,
                'height'   => $h,
                'data_uri' => 'data:image/png;base64,' . base64_encode(file_get_contents($path)),
            ];
        }

        $existing = RentalApplicationDocumentHighlight::where('document_id', $document->id)->first();

        return [
            'pages' => $out,
            'marks' => $existing->marks_json ?? [],
        ];
    }

    /**
     * Burn the current mark set and persist a flattened, marked-up copy.
     * Idempotent — always re-renders from the pristine SOURCE (via the same
     * cache renderSourcePages() reads), so removing a mark and re-applying
     * genuinely removes it. An empty mark set clears the artifact entirely —
     * the next viewer then sees the plain original, not a needless one.
     *
     * @param  array<int, array<int, array>>  $marksByPage  page-index (0-based) => list of marks, RASTER pixel coords.
     */
    public function applyMarks(Document $document, int $agencyId, ?int $userId, array $marksByPage): RentalApplicationDocumentHighlight
    {
        $normalized = $this->normalizeForStorage($marksByPage);
        $flatCount = array_sum(array_map('count', $normalized));

        $highlight = RentalApplicationDocumentHighlight::firstOrNew(['document_id' => $document->id]);
        $highlight->agency_id = $agencyId;
        $highlight->updated_by_user_id = $userId;
        $highlight->marks_json = $normalized;

        if ($flatCount === 0) {
            $this->deleteArtifactIfAny($highlight->highlighted_file_path);
            $highlight->highlighted_file_path = null;
            $highlight->save();

            return $highlight;
        }

        $pagePaths = $this->cachedOrRasterizedPagePaths($document);
        $images = [];

        try {
            foreach ($pagePaths as $i => $path) {
                $img = imagecreatefrompng($path);
                $marks = $normalized[$i] ?? [];
                imagealphablending($img, true);
                foreach ($marks as $m) {
                    $this->burnMark($img, $m);
                }
                $images[$i] = $img;
            }

            $pdfBytes = $this->assemblePdf($images);
        } finally {
            foreach ($images as $img) {
                @imagedestroy($img);
            }
        }

        $rel = 'rental-applications/document-highlights/doc-' . $document->id . '.pdf';
        Storage::disk('local')->put($rel, $pdfBytes);

        $highlight->highlighted_file_path = $rel;
        $highlight->save();

        return $highlight;
    }

    /** @param \GdImage $img */
    private function burnMark($img, array $m): void
    {
        $rgb = self::COLORS[$m['color'] ?? self::DEFAULT_COLOR] ?? self::COLORS[self::DEFAULT_COLOR];
        $fill = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], self::ALPHA);

        if (($m['type'] ?? null) === 'note') {
            $this->burnNote($img, $m, $fill, $rgb);

            return;
        }

        // Highlighter stroke — a real marker-pen gesture (Johan: "click and
        // drag to mark... the way a marker pen works"), not a rectangle: a
        // thick translucent line following the ACTUAL drag path, point to
        // point, with a filled circle at every joint so fast direction
        // changes don't leave visible gaps.
        $points = $m['points'] ?? [];
        if (count($points) < 2) {
            return;
        }
        $half = (int) round((($m['width'] ?? self::STROKE_WIDTH)) / 2);
        imagesetthickness($img, max(1, $half * 2));
        for ($i = 1; $i < count($points); $i++) {
            imageline($img, (int) $points[$i - 1]['x'], (int) $points[$i - 1]['y'], (int) $points[$i]['x'], (int) $points[$i]['y'], $fill);
        }
        foreach ($points as $p) {
            imagefilledellipse($img, (int) $p['x'], (int) $p['y'], $half * 2, $half * 2, $fill);
        }
        imagesetthickness($img, 1);
    }

    /** A pinned note: small marker + the note's own text burned in, so a flattened/downloaded copy still shows it, not just the live in-app view. */
    private function burnNote($img, array $m, int $fill, array $rgb): void
    {
        $x = (int) round((float) ($m['x'] ?? 0));
        $y = (int) round((float) ($m['y'] ?? 0));
        $text = (string) ($m['text'] ?? '');

        $lines = $text === '' ? [] : explode("\n", wordwrap($text, 40, "\n", true));
        $lineHeight = 15;
        $boxW = 260;
        $boxH = 28 + (count($lines) * $lineHeight);

        $opaque = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        $border = imagecolorallocate($img, max(0, $rgb[0] - 60), max(0, $rgb[1] - 60), max(0, $rgb[2] - 60));
        $textColor = imagecolorallocate($img, 40, 40, 40);

        imagefilledellipse($img, $x, $y, 18, 18, $opaque);
        imageellipse($img, $x, $y, 18, 18, $border);

        $boxX = $x + 14;
        $boxY = $y - (int) ($boxH / 2);
        imagefilledrectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $fill);
        imagerectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $border);

        $ty = $boxY + 10;
        foreach ($lines as $line) {
            imagestring($img, 3, $boxX + 8, $ty, $line, $textColor);
            $ty += $lineHeight;
        }
    }

    /**
     * Keep only well-shaped mark data in storage — never trust the raw
     * request payload verbatim into a JSON column.
     */
    private function normalizeForStorage(array $marksByPage): array
    {
        $out = [];
        foreach ($marksByPage as $page => $marks) {
            if (! is_array($marks)) {
                continue;
            }
            $pageIndex = (int) $page;
            foreach ($marks as $m) {
                $color = array_key_exists($m['color'] ?? null, self::COLORS) ? $m['color'] : self::DEFAULT_COLOR;
                $type = ($m['type'] ?? null) === 'note' ? 'note' : 'highlight';

                if ($type === 'note') {
                    $text = trim((string) ($m['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $out[$pageIndex][] = [
                        'type' => 'note',
                        'x' => (float) ($m['x'] ?? 0),
                        'y' => (float) ($m['y'] ?? 0),
                        'text' => mb_substr($text, 0, 1000),
                        'color' => $color,
                    ];

                    continue;
                }

                $points = array_values(array_filter((array) ($m['points'] ?? []), fn ($p) => is_array($p) && isset($p['x'], $p['y'])));
                if (count($points) < 2) {
                    continue;
                }
                $out[$pageIndex][] = [
                    'type' => 'highlight',
                    'points' => array_map(fn ($p) => ['x' => (float) $p['x'], 'y' => (float) $p['y']], $points),
                    'width' => max(4, min(120, (float) ($m['width'] ?? self::STROKE_WIDTH))),
                    'color' => $color,
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
     * Cached, rasterized page PNGs for this exact document version. Reused
     * by every subsequent open (any agent) — pdftoppm only runs once per
     * document, not once per open. Cache key includes the document's own
     * updated_at so a genuinely different file (should the source ever be
     * replaced) rasterizes fresh rather than serving stale pixels.
     *
     * @return array<int, string> page-index (0-based) => absolute PNG file path
     */
    private function cachedOrRasterizedPagePaths(Document $doc): array
    {
        $cacheDir = storage_path('app/private/rental-applications/document-highlights/cache/doc-' . $doc->id . '-v' . $doc->updated_at->timestamp);

        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/page-*.png');
            if (! empty($files)) {
                natsort($files);

                return array_values($files);
            }
        }

        @mkdir($cacheDir, 0775, true);
        $rendered = $this->renderAndCachePages($doc, $cacheDir);

        // Prune any stale cache directories for an older version of this document.
        $this->pruneOldCacheVersions($doc);

        return $rendered;
    }

    /** @return array<int, string> */
    private function renderAndCachePages(Document $doc, string $cacheDir): array
    {
        $bytes = $doc->decryptedContents();
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Source document could not be read.');
        }

        $isPdf = str_contains(strtolower((string) $doc->mime_type), 'pdf');

        if (! $isPdf) {
            $target = $cacheDir . '/page-0.png';
            $img = @imagecreatefromstring($bytes);
            if (! $img) {
                throw new \RuntimeException('Source image could not be read.');
            }
            imagepng($img, $target);
            imagedestroy($img);

            return [0 => $target];
        }

        $tmpFile = sys_get_temp_dir() . '/rental_highlight_src_' . uniqid('', true) . '.pdf';
        file_put_contents($tmpFile, $bytes);

        try {
            return $this->rasterizePdfToCache($tmpFile, $cacheDir);
        } finally {
            @unlink($tmpFile);
        }
    }

    /** @return array<int, string> */
    private function rasterizePdfToCache(string $pdfPath, string $cacheDir): array
    {
        $count = $this->pageCount($pdfPath);
        if ($count < 1) {
            throw new \RuntimeException('Could not determine the PDF page count.');
        }

        $pdftoppm = config('splitter.pdftoppm_path', 'pdftoppm');

        // One process call for the whole document, not one per page — measured
        // against a real 17-page file this saves only ~4% (pdftoppm's own
        // rendering dominates, not process-spawn overhead), but it's free to
        // do and removes 16 unnecessary process spawns.
        $proc = new Process([$pdftoppm, '-png', '-r', (string) self::DPI, $pdfPath, $cacheDir . '/page']);
        $proc->setTimeout(180);
        $proc->run();

        if (! $proc->isSuccessful()) {
            throw new \RuntimeException('pdftoppm failed: ' . trim($proc->getErrorOutput()));
        }

        $files = glob($cacheDir . '/page-*.png');
        if (count($files) < $count) {
            throw new \RuntimeException('pdftoppm produced fewer pages than expected.');
        }
        natsort($files);
        $files = array_values($files);

        // Renumber pdftoppm's 1-based "page-1.png" output to our 0-based
        // page-0.png convention so cachedOrRasterizedPagePaths()'s glob
        // sorts and indexes consistently on every read.
        $out = [];
        foreach ($files as $i => $f) {
            $target = $cacheDir . '/page-' . $i . '.png';
            if ($f !== $target) {
                rename($f, $target);
            }
            $out[$i] = $target;
        }

        return $out;
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

    /** Delete cache directories for older versions of this same document, so a re-uploaded file's cache never grows unbounded. */
    private function pruneOldCacheVersions(Document $doc): void
    {
        $base = storage_path('app/private/rental-applications/document-highlights/cache');
        $currentDir = 'doc-' . $doc->id . '-v' . $doc->updated_at->timestamp;
        foreach ((array) glob($base . '/doc-' . $doc->id . '-v*', GLOB_ONLYDIR) as $dir) {
            if (basename($dir) === $currentDir) {
                continue;
            }
            foreach ((array) glob($dir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    /** @param array<int, \GdImage> $pages */
    private function assemblePdf(array $pages): string
    {
        $first = reset($pages);
        $wPt = imagesx($first) * 72 / self::DPI;
        $hPt = imagesy($first) * 72 / self::DPI;

        $body = '';
        $keys = array_keys($pages);
        $last = end($keys);
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
}
