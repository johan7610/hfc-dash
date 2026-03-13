<?php

namespace App\Services\Docuperfect;

use App\Models\Docuperfect\DocumentCustomField;
use App\Models\Docuperfect\Template;
use Illuminate\Support\Str;

class DocumentTemplateGenerator
{
    /**
     * Generate a web template from parsed docx data and confirmed field mappings.
     *
     * @param array  $parsedData     Output from DocxParserService::parse()
     * @param array  $fieldMappings  Confirmed field mappings from user review
     * @param string $templateName   Human-readable template name
     * @param int    $ownerId        User ID of the creator
     * @return Template
     */
    public function generate(array $parsedData, array $fieldMappings, string $templateName, int $ownerId): Template
    {
        $html = $parsedData['html'];
        $bladeHtml = $this->replaceFieldBlanks($html, $fieldMappings);
        $bladeContent = $this->wrapInBladeTemplate($bladeHtml, $templateName);

        // Save blade file
        $slug = Str::slug($templateName) . '-' . time();
        $bladeRelPath = "docuperfect.web-templates.imported.{$slug}";
        $bladeFilePath = resource_path('views/docuperfect/web-templates/imported/' . $slug . '.blade.php');

        // Ensure directory exists
        $dir = dirname($bladeFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($bladeFilePath, $bladeContent);

        // Build fields_json matching existing template format
        // (id, type, label, pageIndex, assignedTo, field_name, render_type)
        $fieldsJson = [];
        foreach ($fieldMappings as $mapping) {
            $fieldName = str_replace('.', '_', $mapping['key']);
            $fieldsJson[] = [
                'id' => 'web_' . $fieldName,
                'type' => 'placeholder',
                'label' => $mapping['label'],
                'pageIndex' => 0,
                'assignedTo' => $mapping['assigned_to'],
                'field_name' => $fieldName,
                'render_type' => 'web',
                'named_field_id' => null,
                'named_field_name' => null,
            ];
        }

        // Create Template record
        $template = Template::create([
            'name' => $templateName,
            'template_type' => 'imported',
            'render_type' => 'web',
            'blade_view' => $bladeRelPath,
            'page_count' => 1,
            'fields_json' => $fieldsJson,
            'is_global' => true,
            'is_esign' => true,
            'owner_id' => $ownerId,
        ]);

        // Create DocumentCustomField records for custom.* fields
        $sortOrder = 0;
        foreach ($fieldMappings as $mapping) {
            if (Str::startsWith($mapping['key'], 'custom.')) {
                DocumentCustomField::create([
                    'template_id' => $template->id,
                    'field_key' => $mapping['key'],
                    'label' => $mapping['label'],
                    'assigned_to' => $mapping['assigned_to'] ?? 'agent',
                    'field_type' => $mapping['field_type'] ?? 'text',
                    'default_value' => $mapping['default_value'] ?? null,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        return $template;
    }

    /**
     * Replace field-blank spans in HTML with Blade field spans.
     */
    protected function replaceFieldBlanks(string $html, array $fieldMappings): string
    {
        $index = 0;

        return preg_replace_callback(
            '/<span[^>]*\bclass="[^"]*\bfield-blank\b[^"]*"[^>]*>.*?<\/span>/s',
            function ($match) use ($fieldMappings, &$index) {
                if (!isset($fieldMappings[$index])) {
                    $index++;
                    return $match[0];
                }

                $mapping = $fieldMappings[$index];
                $index++;

                // Fields assigned to 'skip' are signature lines — remove them
                // (the signature-block component handles these)
                if (($mapping['assigned_to'] ?? '') === 'skip') {
                    return '';
                }

                $key = $mapping['key'];
                $varName = str_replace('.', '_', $key);

                return '<span class="field" data-field="' . e($key) . '">{{ $' . $varName . " ?? '' }}</span>";
            },
            $html
        );
    }

    /**
     * Detect the character offset where the signature section begins.
     *
     * Scans block elements from the bottom up, marking signature-related elements
     * (party labels, underscores, "signed at", financial summary lines, etc.).
     * Stops when substantive clause content is found.
     *
     * @return int Character offset in $html where signature starts, or -1 if not found.
     */
    private function detectSignatureBoundary(string $html): int
    {
        $wrapped = '<div id="docx-root">' . $html . '</div>';

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('docx-root');
        if (!$root) {
            return -1;
        }

        // Collect direct block-level children
        $blockTags = ['p', 'div', 'table', 'ol', 'ul'];
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE
                && in_array(strtolower($child->nodeName), $blockTags)) {
                $children[] = $child;
            }
        }

        if (empty($children)) {
            return -1;
        }

        $partyLabels = [
            'owner', 'tenant', 'agent', 'lessor', 'lessee',
            'date', 'landlord', 'seller', 'buyer', 'witness',
        ];

        $firstSigElement = null; // the topmost signature element found so far

        // Scan from the bottom up
        for ($i = count($children) - 1; $i >= 0; $i--) {
            $el = $children[$i];
            $text = $el->textContent ?? '';
            $textLower = mb_strtolower($text);

            $isSigElement = false;

            // TEXT PATTERNS
            if (stripos($text, 'signed at') !== false || stripos($text, 'signed by') !== false) {
                $isSigElement = true;
            } elseif (stripos($text, 'signature of') !== false) {
                $isSigElement = true;
            } elseif (stripos($text, 'print name') !== false || stripos($text, 'print naam') !== false) {
                $isSigElement = true;
            } elseif (stripos($text, 'duly authoris') !== false) {
                $isSigElement = true;
            }

            // Pure underscore/whitespace lines
            if (!$isSigElement) {
                $stripped = preg_replace('/[\s_\t]+/', '', $text);
                if (empty($stripped)) {
                    $isSigElement = true;
                }
            }

            // Party label lines: text after stripping underscores/whitespace/tabs
            // consists only of words from the party label set, with at least 2 matches
            if (!$isSigElement) {
                $cleanForLabels = preg_replace('/[_\s\t]+/', ' ', $text);
                $cleanForLabels = trim($cleanForLabels);
                if (!empty($cleanForLabels)) {
                    $words = preg_split('/\s+/', $cleanForLabels);
                    $allMatch = true;
                    $matchCount = 0;
                    foreach ($words as $word) {
                        if (in_array(mb_strtolower($word), $partyLabels)) {
                            $matchCount++;
                        } else {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch && $matchCount >= 2) {
                        $isSigElement = true;
                    }
                }
            }

            // NUMBER PATTERNS (financial summary before signatures)
            if (!$isSigElement) {
                if (stripos($text, 'Net Amount to Owner') !== false) {
                    $isSigElement = true;
                } elseif (stripos($text, "Let's Assist Fee") !== false || stripos($text, 'Lets Assist Fee') !== false) {
                    $isSigElement = true;
                } elseif (stripos($text, "Agent's Service Fee") !== false || stripos($text, 'Service Fee (Including VAT)') !== false) {
                    $isSigElement = true;
                } elseif (stripos($text, 'Total Rental Amount') !== false && strpos($text, '_') !== false) {
                    $isSigElement = true;
                }
            }

            // HEADING PATTERNS — numbered or unnumbered signature headings
            // Catches: "24. Signatures", "24 Signatures", "Signatures", etc.
            // Only when text is short (under 40 chars = a heading, not body text)
            if (!$isSigElement) {
                $trimmedText = trim($text);
                if (mb_strlen($trimmedText) < 40 && preg_match('/^(\d+\.?\s+)?Signatures?\s*$/i', $trimmedText)) {
                    $isSigElement = true;
                }
            }

            if ($isSigElement) {
                $firstSigElement = $el;
                continue;
            }

            // STOP CHECK — substantive text that is NOT a signature pattern
            $strippedText = preg_replace('/[\s_]+/', '', $text);
            if (mb_strlen($strippedText) > 60) {
                break;
            }
        }

        if (!$firstSigElement) {
            return -1;
        }

        // Find the opening tag of the first signature element in the original HTML
        $elHtml = $dom->saveHTML($firstSigElement);
        // Extract just the opening tag to search for
        $tagName = $firstSigElement->nodeName;
        $openingTag = '<' . $tagName;

        // Get the element's text to narrow down the match
        $elText = $firstSigElement->textContent ?? '';

        // Search for this element's HTML in the original $html
        // Use the full rendered HTML of the element to find it
        $pos = strpos($html, $elHtml);
        if ($pos !== false) {
            return $pos;
        }

        // Fallback: search for just the opening portion
        $searchSnippet = substr($elHtml, 0, min(80, strlen($elHtml)));
        $pos = strpos($html, $searchSnippet);
        if ($pos !== false) {
            return $pos;
        }

        return -1;
    }

    /**
     * Wrap generated HTML body in a full Blade template shell.
     */
    protected function wrapInBladeTemplate(string $bodyHtml, string $templateName): string
    {
        // Strip signature section — detectSignatureBoundary scans from bottom up
        $sigOffset = $this->detectSignatureBoundary($bodyHtml);
        if ($sigOffset > 0) {
            $bodyHtml = rtrim(substr($bodyHtml, 0, $sigOffset));
        }
        $title = e($templateName);

        return <<<BLADE
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} — Home Finders Coastal</title>
    <style>
        @page {
            size: A4;
            margin: 18mm 20mm 15mm 20mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.2;
            color: #1a1a1a;
            background: white;
        }

        p {
            margin: 0 0 2pt 0;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 18mm 20mm 15mm 20mm;
            background: white;
        }

        @media screen {
            body {
                background: #e5e7eb;
            }
            .page {
                box-shadow: 0 2px 16px rgba(0,0,0,0.15);
                margin-top: 20px;
                margin-bottom: 20px;
            }
        }

        @media print {
            body { background: white; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }

        .field {
            display: inline-block;
            min-width: 120pt;
            border-bottom: 1px solid #333;
            padding: 1pt 4pt;
            min-height: 14pt;
        }

        .field-short {
            min-width: 40pt;
        }

        strong { font-weight: bold; }
        em { font-style: italic; }
    </style>
</head>
<body>
<div class="page">
    @include('docuperfect.web-templates.components.company-header')

{$bodyHtml}

    @include('docuperfect.web-templates.components.signature-block', ['signing_parties' => \$signing_parties ?? []])
</div>
</body>
</html>
BLADE;
    }
}
