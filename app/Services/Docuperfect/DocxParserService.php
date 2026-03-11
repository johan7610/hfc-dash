<?php

namespace App\Services\Docuperfect;

use ZipArchive;

class DocxParserService
{
    /**
     * Auto-label rules: context pattern => [suggested_label, suggested_key, pillar, assigned_to]
     * Order matters — first match wins.
     */
    protected array $labelRules = [
        // Contact — Lessor/Landlord
        ['pattern' => '/\b(owner|lessor|landlord)\b.*\b(surname|last\s*name)\b/i', 'label' => 'Lessor Surname', 'key' => 'contact.lessor_surname', 'pillar' => 'contact', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\b(owner|lessor|landlord)\b.*\bname\b/i', 'label' => 'Lessor Name', 'key' => 'contact.lessor_name', 'pillar' => 'contact', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\b(lessee|tenant|occupant)\b.*\b(surname|last\s*name)\b/i', 'label' => 'Lessee Surname', 'key' => 'contact.lessee_surname', 'pillar' => 'contact', 'party' => 'lessee', 'confidence' => 'high'],
        ['pattern' => '/\b(lessee|tenant|occupant)\b.*\bname\b/i', 'label' => 'Lessee Name', 'key' => 'contact.lessee_name', 'pillar' => 'contact', 'party' => 'lessee', 'confidence' => 'high'],

        // ID / Passport
        ['pattern' => '/\b(id|identity|passport|registration)\s*(no|number)\b/i', 'label' => 'ID Number', 'key' => 'contact.id_number', 'pillar' => 'contact', 'party' => 'lessor', 'confidence' => 'high'],

        // Contact details
        ['pattern' => '/\b(telephone|cell|phone|tel)\b/i', 'label' => 'Telephone', 'key' => 'contact.telephone', 'pillar' => 'contact', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\bemail\b/i', 'label' => 'Email', 'key' => 'contact.email', 'pillar' => 'contact', 'party' => 'lessor', 'confidence' => 'high'],

        // Property
        ['pattern' => '/\b(property\s*(known\s*as|described|situated)|premises)\b/i', 'label' => 'Property Address', 'key' => 'property.address', 'pillar' => 'property', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\b(erf|stand)\s*(no|number)?\b/i', 'label' => 'Erf Number', 'key' => 'property.erf_number', 'pillar' => 'property', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\bunit\s*(no|number)?\b/i', 'label' => 'Unit Number', 'key' => 'property.unit_number', 'pillar' => 'property', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\bcomplex\b/i', 'label' => 'Complex Name', 'key' => 'property.complex_name', 'pillar' => 'property', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\bsuburb\b/i', 'label' => 'Suburb', 'key' => 'property.suburb', 'pillar' => 'property', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\baddress\b/i', 'label' => 'Address', 'key' => 'property.address', 'pillar' => 'property', 'party' => 'agent', 'confidence' => 'medium'],

        // Deal — financial
        ['pattern' => '/\b(rental|monthly\s*rental|rent)\b/i', 'label' => 'Monthly Rental', 'key' => 'deal.monthly_rental', 'pillar' => 'deal', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\bdeposit\b/i', 'label' => 'Deposit', 'key' => 'deal.deposit', 'pillar' => 'deal', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\bcommission\b/i', 'label' => 'Commission', 'key' => 'deal.commission', 'pillar' => 'deal', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\bvat\b/i', 'label' => 'VAT Amount', 'key' => 'deal.vat_amount', 'pillar' => 'deal', 'party' => 'agent', 'confidence' => 'high'],

        // Deal — dates
        ['pattern' => '/\b(commence|start\s*date|commencement)\b/i', 'label' => 'Lease Start Date', 'key' => 'deal.lease_start', 'pillar' => 'deal', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\b(expir|end\s*date|termination)\b/i', 'label' => 'Lease End Date', 'key' => 'deal.lease_end', 'pillar' => 'deal', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\bday\s*of\b/i', 'label' => 'Signed Day', 'key' => 'deal.signed_day', 'pillar' => 'deal', 'party' => 'agent', 'confidence' => 'high'],
        ['pattern' => '/\bdate\b/i', 'label' => 'Date', 'key' => 'deal.date', 'pillar' => 'deal', 'party' => 'agent', 'confidence' => 'medium'],

        // Banking
        ['pattern' => '/\baccount\s*holder\b/i', 'label' => 'Account Holder', 'key' => 'deal.account_holder', 'pillar' => 'deal', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\bbank\s*name\b/i', 'label' => 'Bank Name', 'key' => 'deal.bank_name', 'pillar' => 'deal', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\baccount\s*(no|number)\b/i', 'label' => 'Account Number', 'key' => 'deal.account_number', 'pillar' => 'deal', 'party' => 'lessor', 'confidence' => 'high'],
        ['pattern' => '/\bbranch\s*code\b/i', 'label' => 'Branch Code', 'key' => 'deal.branch_code', 'pillar' => 'deal', 'party' => 'lessor', 'confidence' => 'high'],

        // Agent
        ['pattern' => '/\bagent\b/i', 'label' => 'Agent Name', 'key' => 'agent.agent_name', 'pillar' => 'agent', 'party' => 'agent', 'confidence' => 'high'],
    ];

    /**
     * Parse a .docx file and return structured data.
     * Synchronous pipeline: Mammoth HTML → field detection → CoreX renderer.
     */
    public function parse(string $filePath): array
    {
        // 1. Verify file exists
        if (!file_exists($filePath)) {
            throw new \RuntimeException(
                'Upload file not found: ' . $filePath
            );
        }

        // 2. Prepare paths
        $outputPath = str_replace(
            '\\', '/',
            storage_path('app/imports/temp/' .
                uniqid('mammoth_') . '.json')
        );

        $scriptPath = str_replace(
            '\\', '/',
            base_path('resources/js/mammoth-convert.mjs')
        );

        $filePath = str_replace('\\', '/', $filePath);

        // Ensure output dir exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 3. Run Mammoth via Node.js
        $cmd = sprintf(
            'node %s %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($filePath),
            escapeshellarg($outputPath)
        );

        \Log::info('DocxParser: running Mammoth', [
            'cmd' => $cmd
        ]);

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            \Log::error('DocxParser: Mammoth failed', [
                'exit_code' => $exitCode,
                'output'    => $error,
                'cmd'       => $cmd,
            ]);
            throw new \RuntimeException(
                'Mammoth conversion failed: ' . $error
            );
        }

        if (!file_exists($outputPath)) {
            throw new \RuntimeException(
                'Mammoth produced no output file. ' .
                'Command: ' . $cmd
            );
        }

        // 4. Read Mammoth output
        $json = file_get_contents($outputPath);
        @unlink($outputPath);

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Mammoth output is invalid JSON: ' .
                json_last_error_msg()
            );
        }

        if (isset($data['error'])) {
            throw new \RuntimeException(
                'Mammoth error: ' . $data['error']
            );
        }

        $html = $data['html'] ?? '';

        if (empty(trim($html))) {
            throw new \RuntimeException(
                'Mammoth returned empty HTML. ' .
                'Is the docx valid and not empty?'
            );
        }

        \Log::info('DocxParser: Mammoth success', [
            'html_length' => strlen($html),
            'warnings'    => $data['messages'] ?? [],
        ]);

        // 5. Detect fields from text
        $fields = $this->detectFieldsFromHtml($html);

        \Log::info('DocxParser: fields detected', [
            'count' => count($fields),
        ]);

        // 6. Inject field spans into HTML
        $html = $this->injectFieldSpans($html, $fields);

        // 7. Apply CoreX document renderer
        try {
            $renderer = new \App\Services\Docuperfect\CorexDocumentRenderer();
            $html = $renderer->render($html);
        } catch (\Throwable $e) {
            // Renderer failure must NOT kill the import
            // Log it and continue with un-rendered HTML
            \Log::error('DocxParser: renderer failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [
            'html'     => $html,
            'fields'   => $fields,
            'warnings' => $data['messages'] ?? [],
        ];
    }

    /**
     * Detect field blanks from Mammoth HTML text and auto-label them.
     * Works on raw HTML string — extracts plain text per paragraph for context matching.
     */
    protected function detectFieldsFromHtml(string $html): array
    {
        // Split HTML into paragraph-level chunks
        preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $html, $pMatches);

        $fields = [];
        $position = 0;

        foreach ($pMatches[1] as $pInner) {
            $lineText = strip_tags($pInner);

            // Find underscore blanks in this paragraph's text
            preg_match_all('/_{2,}%?/', $lineText, $blankMatches, PREG_OFFSET_CAPTURE);

            foreach ($blankMatches[0] as $match) {
                $raw = $match[0];
                $offset = $match[1];

                $contextBefore = mb_substr($lineText, 0, $offset);
                $contextBefore = mb_substr($contextBefore, -150);
                $afterPos = $offset + mb_strlen($raw);
                $contextAfter = mb_substr($lineText, $afterPos, 150);
                $context = trim($contextBefore . ' [___] ' . $contextAfter);

                $label = $this->autoLabel($contextBefore, $contextAfter);

                $fields[] = [
                    'raw' => $raw,
                    'context' => $context,
                    'suggested_label' => $label['label'],
                    'suggested_key' => $label['key'],
                    'pillar' => $label['pillar'],
                    'assigned_to' => $label['party'],
                    'confidence' => $label['confidence'],
                    'position' => $position + $offset,
                ];
            }

            $position += mb_strlen($lineText) + 1;
        }

        // Merge adjacent blanks
        $merged = [];
        foreach ($fields as $field) {
            if (!empty($merged)) {
                $prev = &$merged[count($merged) - 1];
                $gap = $field['position'] - ($prev['position'] + mb_strlen($prev['raw']));
                if ($gap >= 0 && $gap <= 10) {
                    $prev['raw'] .= str_repeat(' ', max(0, $gap)) . $field['raw'];
                    $confidenceRank = ['high' => 3, 'medium' => 2, 'low' => 1];
                    if (($confidenceRank[$field['confidence']] ?? 0) > ($confidenceRank[$prev['confidence']] ?? 0)) {
                        $prev['confidence'] = $field['confidence'];
                    }
                    continue;
                }
                unset($prev);
            }
            $merged[] = $field;
        }
        $fields = $merged;

        // Deduplicate keys
        $keyCounts = [];
        foreach ($fields as &$field) {
            $key = $field['suggested_key'];
            if (!isset($keyCounts[$key])) {
                $keyCounts[$key] = 0;
            }
            $keyCounts[$key]++;
            if ($keyCounts[$key] > 1) {
                $field['suggested_key'] = $key . '_' . $keyCounts[$key];
                $field['suggested_label'] = $field['suggested_label'] . ' ' . $keyCounts[$key];
            }
        }
        unset($field);

        return $fields;
    }

    /**
     * Inject <span class="field-blank"> tags into Mammoth HTML using context-based matching.
     * Each underscore run is matched to its best-fit Vision field by surrounding text similarity.
     * Unmatched blanks get grey "unassigned" pills. Fields with no matching blank are right-pane only.
     */
    private function injectFieldSpans(string $html, array $fields): string
    {
        // Step 1: Find all underscore blanks in the HTML (including <u>___</u> and ___%  patterns)
        $blanks = [];
        // Normalize <u>___</u> to plain ___ first for uniform matching
        $normalizedHtml = preg_replace('/<u>([_\s]+)<\/u>/u', '$1', $html);

        preg_match_all(
            '/_{2,}%?/',
            $normalizedHtml,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[0] as $match) {
            $offset = $match[1];
            $rawMatch = $match[0];

            // Extract surrounding plain text (strip HTML tags) for context comparison
            $beforeRaw = substr($normalizedHtml, max(0, $offset - 120), min($offset, 120));
            $afterRaw = substr($normalizedHtml, $offset + strlen($rawMatch), 120);
            $beforeText = strtolower(trim(strip_tags($beforeRaw)));
            $afterText = strtolower(trim(strip_tags($afterRaw)));

            $blanks[] = [
                'offset' => $offset,
                'length' => strlen($rawMatch),
                'raw' => $rawMatch,
                'context' => $beforeText . ' ' . $afterText,
            ];
        }

        \Log::info('injectFieldSpans: found ' . count($blanks) . ' underscore blanks in HTML');

        // Step 2: Match each Vision field to its best underscore blank by context similarity
        $assignments = []; // blankIdx => fieldIdx
        $usedBlanks = [];

        foreach ($fields as $fieldIdx => $field) {
            $fieldContext = strtolower($field['context'] ?? '');
            $fieldLabel = strtolower($field['suggested_label'] ?? '');
            $searchText = $fieldContext . ' ' . $fieldLabel;

            // Extract meaningful words (4+ chars) from field context
            $words = array_filter(
                preg_split('/[\s\[\]_,.:;()\-]+/', $searchText),
                fn($w) => mb_strlen($w) >= 4
            );

            if (empty($words)) {
                continue;
            }

            $bestScore = 0;
            $bestBlank = -1;

            foreach ($blanks as $blankIdx => $blank) {
                if (isset($usedBlanks[$blankIdx])) {
                    continue;
                }

                $blankContext = $blank['context'];
                $score = 0;

                foreach ($words as $word) {
                    if (str_contains($blankContext, $word)) {
                        $score++;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestBlank = $blankIdx;
                }
            }

            if ($bestBlank >= 0 && $bestScore > 0) {
                $assignments[$bestBlank] = $fieldIdx;
                $usedBlanks[$bestBlank] = true;
            }
        }

        \Log::info('injectFieldSpans: matched ' . count($assignments) . ' of ' . count($fields) . ' fields to blanks');

        // Step 3: Build replacement spans — process in reverse offset order so positions stay valid
        // Sort blanks by offset descending
        $sortedIndices = array_keys($blanks);
        usort($sortedIndices, fn($a, $b) => $blanks[$b]['offset'] - $blanks[$a]['offset']);

        foreach ($sortedIndices as $blankIdx) {
            $blank = $blanks[$blankIdx];

            if (isset($assignments[$blankIdx])) {
                // Matched field — coloured pill with field data
                $fieldIdx = $assignments[$blankIdx];
                $field = $fields[$fieldIdx];
                $label = htmlspecialchars($field['suggested_label'] ?? 'Field', ENT_QUOTES, 'UTF-8');
                $rawEsc = htmlspecialchars($blank['raw'], ENT_QUOTES, 'UTF-8');

                $span = '<span class="field-blank"'
                    . ' data-field-index="' . $fieldIdx . '"'
                    . ' data-raw="' . $rawEsc . '"'
                    . ' data-confidence="' . ($field['confidence'] ?? 'medium') . '"'
                    . ' contenteditable="false">'
                    . $label
                    . '</span>';
            } else {
                // Unmatched blank — grey "unassigned" pill
                $rawEsc = htmlspecialchars($blank['raw'], ENT_QUOTES, 'UTF-8');

                $span = '<span class="field-blank field-unassigned"'
                    . ' data-field-index="-1"'
                    . ' data-raw="' . $rawEsc . '"'
                    . ' data-confidence="unassigned"'
                    . ' contenteditable="false">'
                    . '?'
                    . '</span>';
            }

            $normalizedHtml = substr_replace(
                $normalizedHtml,
                $span,
                $blank['offset'],
                $blank['length']
            );
        }

        $assignedCount = count($assignments);
        $unassignedCount = count($blanks) - $assignedCount;
        $unmatchedFields = count($fields) - $assignedCount;

        \Log::info('Span injection complete', [
            'blanks_total' => count($blanks),
            'fields_total' => count($fields),
            'assigned' => $assignedCount,
            'unassigned_blanks' => $unassignedCount,
            'unmatched_fields' => $unmatchedFields,
        ]);

        return $normalizedHtml;
    }

    /**
     * Extract paragraphs with their runs from the XML DOM.
     */
    protected function extractParagraphs(\DOMDocument $dom): array
    {
        $paragraphs = [];
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $pNodes = $xpath->query('//w:p');

        foreach ($pNodes as $pNode) {
            $runs = [];
            $alignment = 'left';

            // Check paragraph alignment
            $jcNodes = $xpath->query('w:pPr/w:jc', $pNode);
            if ($jcNodes->length > 0) {
                $alignment = $jcNodes->item(0)->getAttribute('w:val') ?: 'left';
            }

            // Get font size from paragraph properties
            $pFontSize = null;
            $pSzNodes = $xpath->query('w:pPr/w:rPr/w:sz', $pNode);
            if ($pSzNodes->length > 0) {
                $halfPt = (int) $pSzNodes->item(0)->getAttribute('w:val');
                if ($halfPt > 0) {
                    $pFontSize = $halfPt / 2; // Convert half-points to points
                }
            }

            $rNodes = $xpath->query('w:r', $pNode);

            foreach ($rNodes as $rNode) {
                $text = '';
                $tNodes = $xpath->query('w:t', $rNode);
                foreach ($tNodes as $tNode) {
                    $text .= $tNode->textContent;
                }

                // Check bold
                $isBold = false;
                $bNodes = $xpath->query('w:rPr/w:b', $rNode);
                if ($bNodes->length > 0) {
                    $val = $bNodes->item(0)->getAttribute('w:val');
                    $isBold = ($val === '' || $val === '1' || $val === 'true');
                }

                // Check italic
                $isItalic = false;
                $iNodes = $xpath->query('w:rPr/w:i', $rNode);
                if ($iNodes->length > 0) {
                    $val = $iNodes->item(0)->getAttribute('w:val');
                    $isItalic = ($val === '' || $val === '1' || $val === 'true');
                }

                // Check underline
                $isUnderline = false;
                $uNodes = $xpath->query('w:rPr/w:u', $rNode);
                if ($uNodes->length > 0) {
                    $val = $uNodes->item(0)->getAttribute('w:val');
                    $isUnderline = ($val !== '' && $val !== 'none');
                }

                // Font size per run
                $fontSize = $pFontSize;
                $szNodes = $xpath->query('w:rPr/w:sz', $rNode);
                if ($szNodes->length > 0) {
                    $halfPt = (int) $szNodes->item(0)->getAttribute('w:val');
                    if ($halfPt > 0) {
                        $fontSize = $halfPt / 2;
                    }
                }

                // Font family per run
                $fontFamily = null;
                $fontNodes = $xpath->query('w:rPr/w:rFonts', $rNode);
                if ($fontNodes->length > 0) {
                    $fontFamily = $fontNodes->item(0)->getAttribute('w:ascii')
                        ?: $fontNodes->item(0)->getAttribute('w:hAnsi');
                }

                if ($text !== '') {
                    $runs[] = [
                        'text' => $text,
                        'bold' => $isBold,
                        'italic' => $isItalic,
                        'underline' => $isUnderline,
                        'fontSize' => $fontSize,
                        'fontFamily' => $fontFamily,
                    ];
                }
            }

            $paragraphs[] = [
                'runs' => $runs,
                'alignment' => $alignment,
            ];
        }

        return $paragraphs;
    }

    /**
     * Build HTML from parsed paragraphs.
     */
    protected function buildHtml(array $paragraphs): string
    {
        $html = '';

        foreach ($paragraphs as $para) {
            $style = '';
            if ($para['alignment'] !== 'left') {
                $align = $para['alignment'] === 'both' ? 'justify' : $para['alignment'];
                $style .= "text-align:{$align};";
            }

            $pAttr = $style ? " style=\"{$style}\"" : '';
            $inner = '';

            foreach ($para['runs'] as $run) {
                $text = htmlspecialchars($run['text'], ENT_QUOTES, 'UTF-8');

                // Check if this is a field blank (3+ underscores)
                if (preg_match('/^_{3,}$/', trim($run['text']))) {
                    $text = '<span class="field-blank" data-raw="' . htmlspecialchars($run['text'], ENT_QUOTES) . '">' . $text . '</span>';
                } else {
                    $runStyle = '';
                    if ($run['fontSize']) {
                        $runStyle .= "font-size:{$run['fontSize']}pt;";
                    }
                    if ($run['fontFamily']) {
                        $runStyle .= "font-family:'{$run['fontFamily']}',sans-serif;";
                    }

                    $spanAttr = $runStyle ? " style=\"{$runStyle}\"" : '';

                    if ($run['bold']) {
                        $text = "<strong>{$text}</strong>";
                    }
                    if ($run['italic']) {
                        $text = "<em>{$text}</em>";
                    }
                    if ($run['underline']) {
                        $text = "<u>{$text}</u>";
                    }
                    if ($spanAttr) {
                        $text = "<span{$spanAttr}>{$text}</span>";
                    }
                }

                $inner .= $text;
            }

            // Skip completely empty paragraphs (but keep ones with just a space for spacing)
            if ($inner === '' && count($para['runs']) === 0) {
                $html .= "<p{$pAttr}>&nbsp;</p>\n";
            } else {
                $html .= "<p{$pAttr}>{$inner}</p>\n";
            }
        }

        return $html;
    }

    /**
     * Build plain text from paragraphs.
     */
    protected function buildPlainText(array $paragraphs): string
    {
        $lines = [];
        foreach ($paragraphs as $para) {
            $line = '';
            foreach ($para['runs'] as $run) {
                $line .= $run['text'];
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    /**
     * Detect field blanks from paragraphs and auto-label them.
     */
    protected function detectFields(array $paragraphs): array
    {
        $fields = [];
        $position = 0;
        $customIndex = 1;

        foreach ($paragraphs as $para) {
            $lineText = '';
            foreach ($para['runs'] as $run) {
                $lineText .= $run['text'];
            }

            // Track character offset within the full document
            $runOffset = $position;

            foreach ($para['runs'] as $run) {
                $text = $run['text'];
                $isBlank = preg_match('/_{2,}/', $text);

                if ($isBlank) {
                    // Gather context: 150 chars before and after from the line
                    $beforeText = mb_substr($lineText, 0, max(0, mb_strpos($lineText, $text)));
                    $contextBefore = mb_substr($beforeText, -150);
                    $afterPos = mb_strpos($lineText, $text) + mb_strlen($text);
                    $contextAfter = mb_substr($lineText, $afterPos, 150);
                    $context = trim($contextBefore . ' [___] ' . $contextAfter);

                    // Auto-label
                    $match = $this->autoLabel($contextBefore, $contextAfter);

                    $fields[] = [
                        'raw' => $text,
                        'context' => $context,
                        'suggested_label' => $match['label'],
                        'suggested_key' => $match['key'],
                        'pillar' => $match['pillar'],
                        'assigned_to' => $match['party'],
                        'confidence' => $match['confidence'],
                        'position' => $runOffset,
                    ];

                    if ($match['pillar'] === 'custom') {
                        $customIndex++;
                    }
                }

                $runOffset += mb_strlen($text);
            }

            $position += mb_strlen($lineText) + 1; // +1 for newline
        }

        // Merge adjacent blanks on the same line (e.g. "___ ___" → one field)
        $merged = [];
        foreach ($fields as $field) {
            if (!empty($merged)) {
                $prev = &$merged[count($merged) - 1];
                $gap = $field['position'] - ($prev['position'] + mb_strlen($prev['raw']));
                if ($gap >= 0 && $gap <= 10) {
                    // Merge into previous field
                    $prev['raw'] .= str_repeat(' ', max(0, $gap)) . $field['raw'];
                    $confidenceRank = ['high' => 3, 'medium' => 2, 'low' => 1];
                    if (($confidenceRank[$field['confidence']] ?? 0) > ($confidenceRank[$prev['confidence']] ?? 0)) {
                        $prev['confidence'] = $field['confidence'];
                    }
                    continue;
                }
                unset($prev);
            }
            $merged[] = $field;
        }
        $fields = $merged;

        // Deduplicate keys — append numeric suffix for repeated keys
        $keyCounts = [];
        foreach ($fields as &$field) {
            $key = $field['suggested_key'];
            if (!isset($keyCounts[$key])) {
                $keyCounts[$key] = 0;
            }
            $keyCounts[$key]++;
            if ($keyCounts[$key] > 1) {
                $field['suggested_key'] = $key . '_' . $keyCounts[$key];
                $field['suggested_label'] = $field['suggested_label'] . ' ' . $keyCounts[$key];
            }
        }
        unset($field);

        return $fields;
    }

    /**
     * Auto-label a field based on surrounding context.
     */
    protected function autoLabel(string $contextBefore, string $contextAfter): array
    {
        $fullContext = $contextBefore . ' ' . $contextAfter;

        foreach ($this->labelRules as $rule) {
            if (preg_match($rule['pattern'], $fullContext)) {
                return [
                    'label' => $rule['label'],
                    'key' => $rule['key'],
                    'pillar' => $rule['pillar'],
                    'party' => $rule['party'],
                    'confidence' => $rule['confidence'],
                ];
            }
        }

        // No match — custom field
        static $customCounter = 0;
        $customCounter++;

        return [
            'label' => 'Custom Field ' . $customCounter,
            'key' => 'custom.field_' . $customCounter,
            'pillar' => 'custom',
            'party' => 'agent',
            'confidence' => 'low',
        ];
    }
}
