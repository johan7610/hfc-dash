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
 *   {id: string, type: 'highlight', points: [{x,y}, ...], width: int,
 *    category: 'income'|'expense'|'unpaid'|null, author_user_id: int|null,
 *    author_name: string|null, author_role: 'agent'|'authoriser'|null}
 *   {id: string, type: 'note', x: float, y: float, text: string,
 *    category, author_user_id, author_name, author_role — same as above}
 * `color` (the old yellow/green/pink/blue scheme) may still appear on marks
 * saved before the six-colour category system landed — kept and rendered
 * as-is rather than force-migrated (see burnMark()).
 *
 * 2026-09-08 — Johan approved a six-colour scheme: HUE = category (income
 * green / expense amber / unpaid red), ROLE = treatment (agent = light fill,
 * authoriser = darker fill + a solid underline in a third, role-neutral
 * shade) — readable in greyscale and colour-blind-safe by design (light vs
 * dark carries role even when hue doesn't). Each mark also now carries a
 * stable id and its author, so a user can edit only their own marks
 * (RentalApplicationMarkOwnershipException) and a genuine save-time
 * collision is caught rather than silently overwritten
 * (RentalApplicationMarkVersionConflictException, backed by the
 * `marks_version` column — see this table's own migration).
 */
class RentalApplicationDocumentHighlightService
{
    private const DPI = 150;

    /** Legacy 4-colour scheme — still rendered as-is for marks saved before the category system (see burnMark()), never assigned to a new mark. */
    public const COLORS = [
        'yellow' => [255, 235, 59],
        'green'  => [76, 217, 100],
        'pink'   => [255, 105, 180],
        'blue'   => [90, 200, 250],
    ];

    private const DEFAULT_COLOR = 'yellow';

    public const CATEGORIES = ['income', 'expense', 'unpaid'];

    private const DEFAULT_CATEGORY = 'unpaid';

    /**
     * Six-colour scheme, approved by Johan 2026-09-08. RGB triples — GD has
     * no notion of a CSS custom property, so this is necessarily its own
     * literal copy; the single source of truth for the browser side is the
     * CSS custom properties consumed in
     * partials/document-highlighter-script.blade.php (cc4 owns their
     * definition). If these two ever need to change, they change together.
     */
    public const CATEGORY_COLORS = [
        'income' => [
            'agent' => [167, 243, 207],       // #a7f3cf
            'authoriser' => [78, 201, 154],   // #4ec99a
            'underline' => [4, 108, 78],      // #046c4e
        ],
        'expense' => [
            'agent' => [253, 232, 168],       // #fde8a8
            'authoriser' => [242, 179, 61],   // #f2b33d
            'underline' => [154, 91, 6],      // #9a5b06
        ],
        'unpaid' => [
            'agent' => [251, 205, 201],       // #fbcdc9
            'authoriser' => [238, 124, 114],  // #ee7c72
            'underline' => [169, 29, 19],     // #a91d13
        ],
    ];

    /** Alpha 0 (opaque) – 127 (fully transparent), GD scale. ~35% opacity, like a real marker. */
    private const ALPHA = 82;

    /** Highlighter stroke thickness in RASTER px at DPI above. */
    private const STROKE_WIDTH = 26;

    /** Underline stroke thickness in RASTER px — a fixed thin rule under the fill, not proportional to the highlighter's own width. */
    private const UNDERLINE_WIDTH = 8;

    /**
     * The document's real, true page count. Public so the controller can
     * enforce completeness of an incoming save (see applyMarks() docblock
     * below) — a save must account for every page or it is rejected, never
     * silently trusted as "the whole document" when it isn't.
     */
    public function totalPageCount(Document $document): int
    {
        $cacheDir = $this->cacheDirFor($document);
        @mkdir($cacheDir, 0775, true);

        return $this->pageCountFor($document, $cacheDir);
    }

    /**
     * Progressive load, 2026-09-08 — Johan's decision on the measured 9.2s
     * cold-open cost for a 17-page document: show page 1 immediately, load
     * the rest behind it, and do NOT trade image sharpness for speed (these
     * are ID documents, payslips, bank statements — detail is the entire
     * point of the screen). Two calls instead of one big pagePreviews():
     * this method for the fast first page + total count, remainingPagePreviews()
     * for the rest. Both share the SAME on-disk cache directory/versioning as
     * before, so a document already fully cached (a repeat open) is just as
     * fast as it always was — this only changes the FIRST-ever open.
     *
     * @return array{page: array{index:int,width:int,height:int,data_uri:string}, total_pages: int, marks: array, marks_version: int}
     */
    public function firstPagePreview(Document $document): array
    {
        $cacheDir = $this->cacheDirFor($document);
        @mkdir($cacheDir, 0775, true);

        $totalPages = $this->pageCountFor($document, $cacheDir);

        $path = $cacheDir . '/page-0.png';
        if (! is_file($path)) {
            $this->rasterizeIntoCache($document, $cacheDir, fromPage: 1, toPage: 1);
        }

        [$w, $h] = getimagesize($path);
        $existing = RentalApplicationDocumentHighlight::where('document_id', $document->id)->first();

        return [
            'page' => ['index' => 0, 'width' => $w, 'height' => $h, 'data_uri' => 'data:image/png;base64,' . base64_encode(file_get_contents($path))],
            'total_pages' => $totalPages,
            'marks' => $existing->marks_json ?? [],
            // 2026-09-08 — the client must echo this back as `base_version`
            // on its next save; a mismatch means someone else's save landed
            // first (see RentalApplicationMarkVersionConflictException).
            'marks_version' => $existing->marks_version ?? 0,
        ];
    }

    /**
     * The remaining pages (index 1..N-1) behind the fast first page above.
     * Called by the frontend immediately after firstPagePreview() resolves —
     * the agent can already be reading/marking page 1 while this runs. ONE
     * pdftoppm call for the whole remainder (not one per page) — the same
     * "batch, don't spawn per page" lesson already measured for the
     * full-document path.
     *
     * @return array{pages: array<int, array{index:int,width:int,height:int,data_uri:string}>}
     */
    public function remainingPagePreviews(Document $document): array
    {
        $cacheDir = $this->cacheDirFor($document);
        @mkdir($cacheDir, 0775, true);

        $totalPages = $this->pageCountFor($document, $cacheDir);

        if ($totalPages > 1 && ! is_file($cacheDir . '/page-1.png')) {
            $this->rasterizeIntoCache($document, $cacheDir, fromPage: 2, toPage: null);
        }

        $out = [];
        for ($i = 1; $i < $totalPages; $i++) {
            $path = $cacheDir . '/page-' . $i . '.png';
            [$w, $h] = getimagesize($path);
            $out[] = ['index' => $i, 'width' => $w, 'height' => $h, 'data_uri' => 'data:image/png;base64,' . base64_encode(file_get_contents($path))];
        }

        $this->pruneOldCacheVersions($document);

        return ['pages' => $out];
    }

    /**
     * Burn the current mark set and persist a flattened, marked-up copy.
     * Idempotent — always re-renders from the pristine SOURCE (via the same
     * cache renderSourcePages() reads), so removing a mark and re-applying
     * genuinely removes it. An empty mark set clears the artifact entirely —
     * the next viewer then sees the plain original, not a needless one.
     *
     * 2026-09-08 — ownership + version, Johan-approved. Marks the CLIENT
     * says are unchanged from a page it already had loaded pass straight
     * through untouched (there is no in-place "move/resize an existing
     * mark" feature — only draw-new and remove); a mark missing from the
     * incoming payload that PREVIOUSLY existed is being removed, and a mark
     * with no matching id is new. Ownership is enforced only on removal —
     * removing someone else's identified mark is refused
     * (RentalApplicationMarkOwnershipException); creating a new one always
     * stamps the CURRENT caller as its author, never trusting a
     * client-supplied author. `$baseVersion` must match the document's
     * current `marks_version` or the whole save is refused
     * (RentalApplicationMarkVersionConflictException) — someone else's save
     * landed first and this client's view is stale.
     *
     * @param  array<int, array<int, array>>  $marksByPage  page-index (0-based) => list of marks, RASTER pixel coords.
     */
    public function applyMarks(Document $document, int $agencyId, int $userId, string $userName, string $authorRole, array $marksByPage, ?int $baseVersion): RentalApplicationDocumentHighlight
    {
        // RA-06, 2026-09-08 — `document_id` is uniquely constrained (one
        // highlight-state row per document, ever — never two live rows for
        // the same document). A plain firstOrNew() only queries through the
        // SoftDeletes global scope, so it can never see a row that was
        // previously soft-deleted (e.g. an admin "clear this document's
        // marks" action, or a support cleanup) — it builds a NEW instance,
        // and the later INSERT collides with the still-physically-present
        // trashed row, raising a raw QueryException. Restoring (not scoping
        // the index to exclude trashed rows) is correct here: a re-created
        // row for the same document_id is always logically the SAME
        // highlight-state record returning, never a distinct one — that is
        // exactly what the unique constraint is encoding. Excluding trashed
        // rows from the index would let two rows exist for one document,
        // which defeats the constraint's purpose instead of fixing the bug.
        $highlight = RentalApplicationDocumentHighlight::withTrashed()->firstOrNew(['document_id' => $document->id]);
        if ($highlight->trashed()) {
            $highlight->restore();
        }

        $currentVersion = (int) ($highlight->marks_version ?? 0);
        if ($highlight->exists && $currentVersion > 0 && $baseVersion !== null && $baseVersion !== $currentVersion) {
            throw new \App\Exceptions\RentalApplicationMarkVersionConflictException($currentVersion);
        }

        $existingByPage = (array) ($highlight->marks_json ?? []);
        $normalized = $this->normalizeForStorage($marksByPage, $existingByPage, $userId, $userName, $authorRole);
        $flatCount = array_sum(array_map('count', $normalized));

        $highlight->agency_id = $agencyId;
        $highlight->updated_by_user_id = $userId;
        $highlight->marks_json = $normalized;
        $highlight->marks_version = $currentVersion + 1;

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

    /**
     * Resolves a mark's fill + underline RGB. Category+role marks (every
     * mark drawn since 2026-09-08) use the six-colour scheme; a genuinely
     * legacy mark (saved before that — has an old `color` key, no
     * `category`) keeps rendering in its original yellow/green/pink/blue,
     * never force-migrated to a category it was never given.
     *
     * @return array{fill: array{int,int,int}, underline: ?array{int,int,int}}
     */
    private function resolveMarkColors(array $m): array
    {
        $category = $m['category'] ?? null;
        if ($category !== null && isset(self::CATEGORY_COLORS[$category])) {
            $role = ($m['author_role'] ?? null) === 'authoriser' ? 'authoriser' : 'agent';
            $scheme = self::CATEGORY_COLORS[$category];

            return ['fill' => $scheme[$role], 'underline' => $scheme['underline']];
        }

        $legacy = self::COLORS[$m['color'] ?? self::DEFAULT_COLOR] ?? self::COLORS[self::DEFAULT_COLOR];

        return ['fill' => $legacy, 'underline' => null];
    }

    /** @param \GdImage $img */
    private function burnMark($img, array $m): void
    {
        $colors = $this->resolveMarkColors($m);
        $rgb = $colors['fill'];
        $fill = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], self::ALPHA);

        if (($m['type'] ?? null) === 'note') {
            $this->burnNote($img, $m, $fill, $rgb, $colors['underline']);

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

        // Six-colour scheme, 2026-09-08 — a solid, OPAQUE thin rule under
        // the stroke in the category's role-neutral "underline" shade. Same
        // vertical-offset simplification the live client uses (see
        // strokesSvgFor() in the shared JS factory): real strokes on this
        // screen are drawn over roughly-horizontal statement lines, so a
        // fixed downward offset reads as an underline for the documents
        // this tool actually sees, without needing true perpendicular-to-
        // path geometry for an arbitrary drag angle.
        if ($colors['underline'] !== null) {
            $u = imagecolorallocate($img, $colors['underline'][0], $colors['underline'][1], $colors['underline'][2]);
            $offset = (int) round($half * 0.9);
            imagesetthickness($img, self::UNDERLINE_WIDTH);
            for ($i = 1; $i < count($points); $i++) {
                imageline(
                    $img,
                    (int) $points[$i - 1]['x'], (int) $points[$i - 1]['y'] + $offset,
                    (int) $points[$i]['x'], (int) $points[$i]['y'] + $offset,
                    $u,
                );
            }
            imagesetthickness($img, 1);
        }
    }

    /**
     * A pinned note: small marker + the note's own text burned in, so a
     * flattened/downloaded copy still shows it, not just the live in-app view.
     *
     * 2026-09-08 — Johan, testing the authoriser's flattened "View marked-up
     * copy": "notes are visible. small and almost too small". Root cause:
     * this burned text with GD's built-in bitmap font (imagestring font
     * index 3), a fixed ~13px glyph height that does not scale with the
     * page image's DPI::DPI (150) — legible in the small preview thumbnails
     * ViewingPackRedactionService was built for, but tiny against a
     * full-resolution page. Switched to a scalable TTF font sized relative
     * to DPI, matching how Docuperfect\DocumentFlattener sizes its own
     * burned-in text elsewhere in this codebase.
     */
    private function burnNote($img, array $m, int $fill, array $rgb, ?array $underlineRgb): void
    {
        $x = (int) round((float) ($m['x'] ?? 0));
        $y = (int) round((float) ($m['y'] ?? 0));
        $text = (string) ($m['text'] ?? '');

        $font = $this->findFont();
        $fontSize = self::DPI * 0.16; // ≈24px at 150 DPI — readable at full page resolution
        $lineHeight = (int) round($fontSize * 1.35);
        $wrapWidth = $font ? 32 : 40;

        $lines = $text === '' ? [] : explode("\n", wordwrap($text, $wrapWidth, "\n", true));
        $boxW = 420;
        $boxH = 36 + (count($lines) * $lineHeight);

        $opaque = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        // Six-colour scheme, 2026-09-08 — the marker dot's border carries the
        // category's role-neutral "underline" shade (same signal an
        // underline gives a stroke); legacy marks with no category keep the
        // old darkened-fill border they always had.
        $border = $underlineRgb !== null
            ? imagecolorallocate($img, $underlineRgb[0], $underlineRgb[1], $underlineRgb[2])
            : imagecolorallocate($img, max(0, $rgb[0] - 60), max(0, $rgb[1] - 60), max(0, $rgb[2] - 60));
        $textColor = imagecolorallocate($img, 40, 40, 40);

        imagefilledellipse($img, $x, $y, 18, 18, $opaque);
        imageellipse($img, $x, $y, 18, 18, $border);

        $boxX = $x + 14;
        $boxY = $y - (int) ($boxH / 2);
        imagefilledrectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $fill);
        imagerectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $border);

        $ty = $boxY + 14 + (int) round($fontSize);
        foreach ($lines as $line) {
            if ($font && function_exists('imagettftext')) {
                imagettftext($img, $fontSize, 0, $boxX + 10, $ty, $textColor, $font, $line);
            } else {
                imagestring($img, 5, $boxX + 10, $ty - $lineHeight + 4, $line, $textColor);
            }
            $ty += $lineHeight;
        }
    }

    /** Regular-weight TTF for burned-in note text — same candidate paths DocumentFlattener resolves elsewhere in this codebase. */
    private function findFont(): ?string
    {
        $candidates = [
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            'C:/Windows/Fonts/calibri.ttf',
            resource_path('fonts/arial.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Keep only well-shaped mark data in storage — never trust the raw
     * request payload verbatim into a JSON column. Also where ownership is
     * enforced (2026-09-08, Johan): every mark id already present in
     * $existingByPage is matched against the incoming payload —
     *   - present in both → pass through the STORED copy untouched (there is
     *     no in-place edit of an existing mark's shape/text, only draw-new
     *     and remove, so the incoming copy is never trusted over storage);
     *   - missing from incoming but present in storage → being removed;
     *     only allowed if its author is $userId or unattributed (null);
     *   - present in incoming with no matching id → a genuinely new mark,
     *     always stamped with the CURRENT caller as author, never a
     *     client-supplied one.
     *
     * @throws \App\Exceptions\RentalApplicationMarkOwnershipException
     */
    private function normalizeForStorage(array $marksByPage, array $existingByPage, int $userId, string $userName, string $authorRole): array
    {
        $out = [];
        $pages = array_unique(array_merge(
            array_map('intval', array_keys($marksByPage)),
            array_map('intval', array_keys($existingByPage)),
        ));

        foreach ($pages as $pageIndex) {
            $incoming = (array) ($marksByPage[$pageIndex] ?? $marksByPage[(string) $pageIndex] ?? []);

            // Marks saved before the category/id/author scheme have no id at
            // all — kept in a separate pool matched by CONTENT below, so an
            // unchanged legacy mark round-trips exactly instead of being
            // silently "claimed" by whoever happens to save next (Johan:
            // legacy marks stay honestly unattributed, never guessed).
            $existingById = [];
            $legacyPool = [];
            foreach ((array) ($existingByPage[$pageIndex] ?? $existingByPage[(string) $pageIndex] ?? []) as $idx => $m) {
                if (isset($m['id']) && is_string($m['id']) && $m['id'] !== '') {
                    $existingById[$m['id']] = $m;
                } else {
                    $legacyPool[$idx] = $m;
                }
            }

            $claimedIds = [];
            $normalizedPage = [];

            foreach ($incoming as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $id = isset($m['id']) && is_string($m['id']) && $m['id'] !== '' ? $m['id'] : null;

                if ($id !== null && isset($existingById[$id])) {
                    // Existing mark, echoed back — pass through storage's own
                    // copy verbatim. Never trust the client's copy of a mark
                    // it doesn't necessarily own (see docblock above).
                    $claimedIds[$id] = true;
                    $normalizedPage[] = $existingById[$id];

                    continue;
                }

                if ($id === null) {
                    $legacyIdx = $this->findLegacyMatch($m, $legacyPool);
                    if ($legacyIdx !== null) {
                        $normalizedPage[] = $legacyPool[$legacyIdx];
                        unset($legacyPool[$legacyIdx]);

                        continue;
                    }
                }

                // A genuinely new mark.
                $normalized = $this->normalizeNewMark($m, $userId, $userName, $authorRole);
                if ($normalized === null) {
                    continue;
                }
                if ($id !== null) {
                    $claimedIds[$id] = true;
                }
                $normalizedPage[] = $normalized;
            }

            // Anything in storage but NOT echoed back is being removed —
            // only the mark's own author (or nobody, if unattributed) may do
            // that. Unmatched legacy-pool marks are always removable
            // (nothing to protect — no author).
            foreach ($existingById as $id => $existingMark) {
                if (isset($claimedIds[$id])) {
                    continue;
                }
                $author = $existingMark['author_user_id'] ?? null;
                if ($author !== null && (int) $author !== $userId) {
                    throw new \App\Exceptions\RentalApplicationMarkOwnershipException($id);
                }
                // else: legitimately removed (own mark, or unattributed legacy mark).
            }

            if (! empty($normalizedPage)) {
                $out[$pageIndex] = $normalizedPage;
            }
        }

        return $out;
    }

    /**
     * Legacy marks (saved before the id/category/author scheme) have
     * nothing to match on except their own shape — finds an existing
     * legacy-pool mark that is the SAME mark as $incoming (same type, same
     * geometry/text, small float tolerance for the raster<->display px
     * round trip), so it can pass through unchanged rather than being
     * treated as newly created.
     */
    private function findLegacyMatch(array $incoming, array $pool): ?int
    {
        foreach ($pool as $idx => $existing) {
            if (($existing['type'] ?? null) !== ($incoming['type'] ?? null)) {
                continue;
            }
            if (($existing['type'] ?? null) === 'note') {
                if (abs((float) ($existing['x'] ?? 0) - (float) ($incoming['x'] ?? 0)) < 1.5
                    && abs((float) ($existing['y'] ?? 0) - (float) ($incoming['y'] ?? 0)) < 1.5
                    && (string) ($existing['text'] ?? '') === (string) ($incoming['text'] ?? '')) {
                    return $idx;
                }

                continue;
            }

            $ep = (array) ($existing['points'] ?? []);
            $ip = (array) ($incoming['points'] ?? []);
            if (count($ep) !== count($ip)) {
                continue;
            }
            $match = true;
            foreach ($ep as $i => $pt) {
                if (! isset($ip[$i]) || abs((float) ($pt['x'] ?? 0) - (float) ($ip[$i]['x'] ?? 0)) > 1.5 || abs((float) ($pt['y'] ?? 0) - (float) ($ip[$i]['y'] ?? 0)) > 1.5) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $idx;
            }
        }

        return null;
    }

    /** @return array|null null if the mark is malformed and should be silently dropped (shape already validated at the HTTP layer — this is the belt to that braces). */
    private function normalizeNewMark(array $m, int $userId, string $userName, string $authorRole): ?array
    {
        $type = ($m['type'] ?? null) === 'note' ? 'note' : 'highlight';
        $category = in_array($m['category'] ?? null, self::CATEGORIES, true) ? $m['category'] : self::DEFAULT_CATEGORY;
        $id = isset($m['id']) && is_string($m['id']) && $m['id'] !== '' ? mb_substr($m['id'], 0, 64) : (string) \Illuminate\Support\Str::uuid();

        $base = [
            'id' => $id,
            'category' => $category,
            'author_user_id' => $userId,
            'author_name' => mb_substr($userName, 0, 100),
            'author_role' => $authorRole,
        ];

        if ($type === 'note') {
            $text = trim((string) ($m['text'] ?? ''));
            if ($text === '') {
                return null;
            }

            return $base + [
                'type' => 'note',
                'x' => (float) ($m['x'] ?? 0),
                'y' => (float) ($m['y'] ?? 0),
                'text' => mb_substr($text, 0, 1000),
            ];
        }

        $points = array_values(array_filter((array) ($m['points'] ?? []), fn ($p) => is_array($p) && isset($p['x'], $p['y'])));
        if (count($points) < 2) {
            return null;
        }

        return $base + [
            'type' => 'highlight',
            'points' => array_map(fn ($p) => ['x' => (float) $p['x'], 'y' => (float) $p['y']], $points),
            'width' => max(4, min(120, (float) ($m['width'] ?? self::STROKE_WIDTH))),
        ];
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
     * Used by applyMarks() to burn+assemble the FULL flattened PDF — this
     * must always return every page, never a partial set. 2026-09-08 —
     * since firstPagePreview()/remainingPagePreviews() can now leave the
     * cache dir PARTIALLY populated (just page-0.png, if an agent saves
     * while the rest are still loading behind it), the old "any file
     * present = fully cached" check would have silently assembled a
     * one-page PDF and dropped the rest. Now verifies the file COUNT
     * matches the real page count before trusting the cache.
     *
     * @return array<int, string> page-index (0-based) => absolute PNG file path
     */
    private function cachedOrRasterizedPagePaths(Document $doc): array
    {
        $cacheDir = $this->cacheDirFor($doc);
        @mkdir($cacheDir, 0775, true);

        $totalPages = $this->pageCountFor($doc, $cacheDir);
        $files = glob($cacheDir . '/page-*.png');

        if (count($files) < $totalPages) {
            $this->rasterizeIntoCache($doc, $cacheDir, fromPage: 1, toPage: null);
            $files = glob($cacheDir . '/page-*.png');
        }

        if (count($files) < $totalPages) {
            throw new \RuntimeException('Rasterization produced fewer pages than expected.');
        }

        natsort($files);
        $this->pruneOldCacheVersions($doc);

        return array_values($files);
    }

    private function cacheDirFor(Document $doc): string
    {
        return storage_path('app/private/rental-applications/document-highlights/cache/doc-' . $doc->id . '-v' . $doc->updated_at->timestamp);
    }

    /**
     * Real page count for this document, cached to a marker file so a
     * SECOND request (e.g. remainingPagePreviews() right after
     * firstPagePreview()) never re-decrypts the source or re-runs pdfinfo
     * just to learn a number the first request already worked out.
     */
    private function pageCountFor(Document $doc, string $cacheDir): int
    {
        $marker = $cacheDir . '/total-pages.txt';
        if (is_file($marker)) {
            $count = (int) trim((string) file_get_contents($marker));
            if ($count > 0) {
                return $count;
            }
        }

        $isPdf = str_contains(strtolower((string) $doc->mime_type), 'pdf');
        if (! $isPdf) {
            file_put_contents($marker, '1');

            return 1;
        }

        $bytes = $doc->decryptedContents();
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Source document could not be read.');
        }

        $tmpFile = sys_get_temp_dir() . '/rental_highlight_src_' . uniqid('', true) . '.pdf';
        file_put_contents($tmpFile, $bytes);

        try {
            $proc = new Process(['pdfinfo', $tmpFile]);
            $proc->setTimeout(30);
            $proc->run();

            $count = 0;
            if ($proc->isSuccessful() && preg_match('/Pages:\s+(\d+)/', $proc->getOutput(), $m)) {
                $count = (int) $m[1];
            }
            if ($count < 1) {
                throw new \RuntimeException('Could not determine the PDF page count.');
            }

            file_put_contents($marker, (string) $count);

            return $count;
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Rasterize a page range straight into the cache dir, 0-based
     * page-N.png naming. $fromPage/$toPage are 1-based (pdftoppm's own
     * convention) — pass toPage: null for "to the end of the document".
     * Handles the non-PDF single-image case too (fromPage must be 1 there).
     */
    private function rasterizeIntoCache(Document $doc, string $cacheDir, int $fromPage, ?int $toPage): void
    {
        $isPdf = str_contains(strtolower((string) $doc->mime_type), 'pdf');

        if (! $isPdf) {
            $bytes = $doc->decryptedContents();
            if ($bytes === null || $bytes === '') {
                throw new \RuntimeException('Source document could not be read.');
            }
            $img = @imagecreatefromstring($bytes);
            if (! $img) {
                throw new \RuntimeException('Source image could not be read.');
            }
            imagepng($img, $cacheDir . '/page-0.png');
            imagedestroy($img);

            return;
        }

        $bytes = $doc->decryptedContents();
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Source document could not be read.');
        }

        $tmpFile = sys_get_temp_dir() . '/rental_highlight_src_' . uniqid('', true) . '.pdf';
        file_put_contents($tmpFile, $bytes);

        try {
            $pdftoppm = config('splitter.pdftoppm_path', 'pdftoppm');
            $tmpPrefix = $cacheDir . '/.raw-' . uniqid('', true);

            $args = [$pdftoppm, '-png', '-r', (string) self::DPI, '-f', (string) $fromPage];
            if ($toPage !== null) {
                $args[] = '-l';
                $args[] = (string) $toPage;
            }
            $args[] = $tmpFile;
            $args[] = $tmpPrefix;

            $proc = new Process($args);
            $proc->setTimeout(180);
            $proc->run();

            if (! $proc->isSuccessful()) {
                throw new \RuntimeException('pdftoppm failed: ' . trim($proc->getErrorOutput()));
            }

            // pdftoppm names output by the ORIGINAL (1-based, zero-padded)
            // page number regardless of -f — e.g. "-f 2" produces
            // "prefix-02.png", not "prefix-01.png". Renumber to our 0-based
            // page-N.png convention on the way in.
            $files = glob($tmpPrefix . '-*.png');
            if (empty($files)) {
                throw new \RuntimeException('pdftoppm produced no output for the requested page range.');
            }
            foreach ($files as $f) {
                if (! preg_match('/-(\d+)\.png$/', $f, $m)) {
                    continue;
                }
                $originalPageNum = (int) $m[1];
                rename($f, $cacheDir . '/page-' . ($originalPageNum - 1) . '.png');
            }
        } finally {
            @unlink($tmpFile);
        }
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
