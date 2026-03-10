<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyDocuPerfectTemplate extends Command
{
    protected $signature = 'docuperfect:verify-template
                            {template? : Template slug (e.g. rental-application-v8)}
                            {--all : Verify all 6 templates in sequence}';

    protected $description = 'Verify blade web-templates word-for-word against their source docx files';

    /**
     * Map template slugs to their source docx filenames.
     */
    private const TEMPLATE_MAP = [
        'rental-application-v8'          => 'RENTAL APPLICATION (V8).docx',
        'letting-mandatory-disclosure-v7' => 'Letting Mandatory Disclosure (V7).docx',
        'letting-marketing-permission-v7' => 'Letting Marketing permission (V7).docx',
        'letting-mandate-v5'             => 'Letting Mandate (V5).docx',
        'lease-agreement-popi-v8'        => 'Lease agreement - Popi (V8).docx',
        'commercial-lease-agreement-v5'  => 'Commercial Lease agreement (V5).docx',
    ];

    public function handle(): int
    {
        $template = $this->argument('template');
        $all = $this->option('all');

        if (! $template && ! $all) {
            $this->error('Provide a template slug or use --all to verify all templates.');
            $this->line('');
            $this->line('Available templates:');
            foreach (array_keys(self::TEMPLATE_MAP) as $slug) {
                $this->line("  - {$slug}");
            }
            return self::FAILURE;
        }

        $templates = $all
            ? array_keys(self::TEMPLATE_MAP)
            : [$template];

        $totalDiffs = 0;
        $results = [];

        foreach ($templates as $slug) {
            if (! isset(self::TEMPLATE_MAP[$slug])) {
                $this->error("Unknown template: {$slug}");
                $this->line('Valid templates: ' . implode(', ', array_keys(self::TEMPLATE_MAP)));
                return self::FAILURE;
            }

            $this->newLine();
            $this->info("═══ Verifying: {$slug} ═══");

            $diffCount = $this->verifyTemplate($slug);
            $totalDiffs += $diffCount;
            $results[$slug] = $diffCount;
        }

        // Summary
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('         VERIFICATION SUMMARY');
        $this->info('═══════════════════════════════════════');

        foreach ($results as $slug => $count) {
            if ($count === 0) {
                $this->line("  ✓ {$slug} — MATCH");
            } elseif ($count === -1) {
                $this->error("  ✗ {$slug} — ERROR (could not verify)");
            } else {
                $this->warn("  ✗ {$slug} — {$count} difference(s)");
            }
        }

        $this->newLine();
        if ($totalDiffs === 0) {
            $this->info('All templates verified successfully.');
        } else {
            $this->warn("Total differences across all templates: {$totalDiffs}");
        }

        return $totalDiffs === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Verify a single template. Returns number of differences, or -1 on error.
     */
    private function verifyTemplate(string $slug): int
    {
        $docxFile = self::TEMPLATE_MAP[$slug];
        $docxPath = resource_path("docs/source/{$docxFile}");
        $bladeView = "docuperfect.web-templates.{$slug}";

        // 1. Check docx exists
        if (! file_exists($docxPath)) {
            $this->error("  Source docx not found: {$docxPath}");
            return -1;
        }

        // 2. Extract text from docx
        $sourceText = $this->extractDocxText($docxPath);
        if ($sourceText === null) {
            $this->error("  Failed to extract text from docx: {$docxPath}");
            return -1;
        }

        // 3. Render blade to HTML
        try {
            $html = view($bladeView)->render();
        } catch (\Throwable $e) {
            $this->error("  Failed to render blade view: {$e->getMessage()}");
            return -1;
        }

        // 4. Strip HTML → plain text
        $bladeText = $this->htmlToText($html);

        // 5. Normalize both
        $sourceNorm = $this->normalize($sourceText);
        $bladeNorm = $this->normalize($bladeText);

        // 6. Compare
        if ($sourceNorm === $bladeNorm) {
            $this->info("  ✓ VERIFIED — {$slug} matches source docx");
            return 0;
        }

        // 7. Diff word-by-word
        $diffs = $this->diffTexts($sourceNorm, $bladeNorm);

        foreach ($diffs as $i => $diff) {
            $num = $i + 1;
            $this->newLine();
            $this->warn("  DIFF [{$num}]:");
            $this->line("    SOURCE: {$diff['source']}");
            $this->line("    BLADE:  {$diff['blade']}");
        }

        $count = count($diffs);
        $this->newLine();
        $this->warn("  {$count} difference(s) found in {$slug}");

        return $count;
    }

    /**
     * Extract plain text from a docx file using ZipArchive + DOMDocument.
     */
    private function extractDocxText(string $path): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        // Parse with DOMDocument
        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $text = '';
        $paragraphs = $dom->getElementsByTagNameNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'p'
        );

        foreach ($paragraphs as $paragraph) {
            $runs = $paragraph->getElementsByTagNameNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'r'
            );

            $paragraphText = '';
            foreach ($runs as $run) {
                $textNodes = $run->getElementsByTagNameNS(
                    'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                    't'
                );
                foreach ($textNodes as $textNode) {
                    $paragraphText .= $textNode->textContent;
                }
            }

            if ($paragraphText !== '') {
                $text .= $paragraphText . "\n";
            }
        }

        return $text;
    }

    /**
     * Convert HTML to plain text — strip tags, decode entities.
     */
    private function htmlToText(string $html): string
    {
        // Remove style and script blocks entirely
        $html = preg_replace('#<style[^>]*>.*?</style>#si', '', $html);
        $html = preg_replace('#<script[^>]*>.*?</script>#si', '', $html);

        // Remove HTML comments (including Blade comments rendered as HTML)
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Convert <br>, <p>, <div>, <tr>, <li> to newlines for paragraph separation
        $html = preg_replace('#<br\s*/?\s*>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|li|h[1-6])>#i', "\n", $html);

        // Strip remaining tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text;
    }

    /**
     * Normalize text: collapse whitespace, trim, preserve words/punctuation/case.
     */
    private function normalize(string $text): string
    {
        // Replace non-breaking spaces with regular spaces
        $text = str_replace("\xC2\xA0", ' ', $text);

        // Collapse all whitespace (spaces, tabs, newlines) to single space
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Diff two normalized strings by splitting into words and finding differences.
     * Returns array of ['source' => ..., 'blade' => ...] showing context around each diff.
     */
    private function diffTexts(string $source, string $blade): array
    {
        $sourceWords = explode(' ', $source);
        $bladeWords = explode(' ', $blade);

        // Use longest common subsequence to find diffs
        $diffs = [];
        $lcs = $this->computeLCS($sourceWords, $bladeWords);

        $si = 0;
        $bi = 0;
        $li = 0;
        $contextSize = 5; // words of context around each diff

        while ($si < count($sourceWords) || $bi < count($bladeWords)) {
            if ($li < count($lcs)
                && $si < count($sourceWords)
                && $bi < count($bladeWords)
                && $sourceWords[$si] === $lcs[$li]
                && $bladeWords[$bi] === $lcs[$li]
            ) {
                // Matching word — advance all pointers
                $si++;
                $bi++;
                $li++;
            } else {
                // Mismatch — collect differing words
                $srcDiff = [];
                $bldDiff = [];

                // Collect source words not in LCS
                while ($si < count($sourceWords)
                    && ($li >= count($lcs) || $sourceWords[$si] !== $lcs[$li])
                ) {
                    $srcDiff[] = $sourceWords[$si];
                    $si++;
                }

                // Collect blade words not in LCS
                while ($bi < count($bladeWords)
                    && ($li >= count($lcs) || $bladeWords[$bi] !== $lcs[$li])
                ) {
                    $bldDiff[] = $bladeWords[$bi];
                    $bi++;
                }

                if (count($srcDiff) > 0 || count($bldDiff) > 0) {
                    // Add context before
                    $contextStart = max(0, $si - count($srcDiff) - $contextSize);
                    $contextBefore = array_slice($sourceWords, $contextStart, $si - count($srcDiff) - $contextStart);
                    $contextAfterStart = $si;
                    $contextAfter = array_slice($sourceWords, $contextAfterStart, $contextSize);

                    $srcContext = implode(' ', $contextBefore)
                        . ' >>>' . implode(' ', $srcDiff) . '<<< '
                        . implode(' ', $contextAfter);

                    $bldContext = implode(' ', $contextBefore)
                        . ' >>>' . implode(' ', $bldDiff) . '<<< '
                        . implode(' ', $contextAfter);

                    $diffs[] = [
                        'source' => trim($srcContext),
                        'blade'  => trim($bldContext),
                    ];
                }
            }
        }

        return $diffs;
    }

    /**
     * Compute Longest Common Subsequence of two word arrays.
     * Uses a memory-efficient approach for large arrays.
     */
    private function computeLCS(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        // For larger arrays, use Hirschberg's O(min(m,n)) space algorithm
        if ($m > 1500 || $n > 1500) {
            return $this->computeLCSHirschberg($a, $b);
        }

        // Standard DP approach for smaller arrays
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        // Backtrack to find LCS
        $lcs = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($lcs, $a[$i - 1]);
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $lcs;
    }

    /**
     * Hirschberg's algorithm for LCS — O(min(m,n)) space.
     */
    private function computeLCSHirschberg(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        if ($m === 0) {
            return [];
        }
        if ($n === 0) {
            return [];
        }
        if ($m === 1) {
            return in_array($a[0], $b, true) ? [$a[0]] : [];
        }

        $mid = intdiv($m, 2);
        $aTop = array_slice($a, 0, $mid);
        $aBot = array_slice($a, $mid);

        $topRow = $this->lcsLengths($aTop, $b);
        $botRow = $this->lcsLengths(array_reverse($aBot), array_reverse($b));
        $botRowRev = array_reverse($botRow);

        // Find optimal split point
        $maxSum = -1;
        $split = 0;
        for ($j = 0; $j <= $n; $j++) {
            $sum = $topRow[$j] + $botRowRev[$j];
            if ($sum > $maxSum) {
                $maxSum = $sum;
                $split = $j;
            }
        }

        $bLeft = array_slice($b, 0, $split);
        $bRight = array_slice($b, $split);

        return array_merge(
            $this->computeLCSHirschberg($aTop, $bLeft),
            $this->computeLCSHirschberg($aBot, $bRight)
        );
    }

    /**
     * Compute last row of LCS DP matrix — O(n) space.
     */
    private function lcsLengths(array $a, array $b): array
    {
        $n = count($b);
        $prev = array_fill(0, $n + 1, 0);

        foreach ($a as $ai) {
            $curr = array_fill(0, $n + 1, 0);
            for ($j = 1; $j <= $n; $j++) {
                if ($ai === $b[$j - 1]) {
                    $curr[$j] = $prev[$j - 1] + 1;
                } else {
                    $curr[$j] = max($curr[$j - 1], $prev[$j]);
                }
            }
            $prev = $curr;
        }

        return $prev;
    }
}
