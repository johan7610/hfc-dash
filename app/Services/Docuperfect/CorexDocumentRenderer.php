<?php

namespace App\Services\Docuperfect;

use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;

class CorexDocumentRenderer
{
    // Signature section trigger phrases
    private array $signatureTriggers = [
        'thus done and signed',
        'signed at',
        'as witness',
        'name of witness',
        'thus signed',
        'signature of',
        'signed by the lessor',
        'signed by the lessee',
        'signed by the agent',
    ];

    // Banking/input row label patterns
    private array $inputRowPatterns = [
        'account holder',
        'bank name',
        'account number',
        'branch code',
        'branch name',
        'reference',
        'id number',
        'id/passport',
        'registration no',
        'vat number',
        'contact number',
        'email address',
        'telephone',
        'cell',
        'fax',
        'erf no',
        'unit no',
        'complex',
        'street address',
    ];

    public function render(string $mammothHtml): string
    {
        if (empty(trim($mammothHtml))) {
            return '';
        }

        // 1. Clean and parse HTML
        $html = $this->cleanMammothHtml($mammothHtml);

        // 2. Transform structure
        $html = $this->transformStructure($html);

        // 3. Apply clause numbering awareness
        $html = $this->processClauseNumbering($html);

        // 4. Detect and render input rows
        $html = $this->processInputRows($html);

        // 5. Signature sections — handled by DocumentTemplateGenerator
        // via @include('signature-block') component, not here.
        // $html = $this->replaceSignatureSections($html);

        return $html;
    }

    private function cleanMammothHtml(string $html): string
    {
        // Remove empty paragraphs that are just <br> or whitespace
        $html = preg_replace(
            '/<p[^>]*>\s*(<br\s*\/?>\s*)*<\/p>/i',
            '',
            $html
        );

        // Normalize multiple spaces
        $html = preg_replace('/\s{3,}/', ' ', $html);

        // Remove Word-specific attributes
        $html = preg_replace('/\s(class|style)="[^"]*mso[^"]*"/i', '', $html);

        return trim($html);
    }

    private function transformStructure(string $html): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' .
            '<div id="corex-doc">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $container = $xpath->query('//div[@id="corex-doc"]')->item(0);

        if (!$container) {
            return $html;
        }

        // Process each child node
        foreach (iterator_to_array($container->childNodes) as $node) {
            if (!($node instanceof DOMElement)) continue;

            $tag = strtolower($node->nodeName);
            $text = trim($node->textContent);

            // Headings — add corex class
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4'])) {
                $existing = $node->getAttribute('class');
                $node->setAttribute(
                    'class',
                    trim($existing . ' corex-heading')
                );
                continue;
            }

            // Paragraphs
            if ($tag === 'p') {
                $existing = $node->getAttribute('class');

                // All caps short text = section heading
                if (
                    strlen($text) < 80 &&
                    strtoupper($text) === $text &&
                    strlen($text) > 3
                ) {
                    $node->setAttribute(
                        'class',
                        trim($existing . ' corex-section-heading')
                    );
                    continue;
                }

                // Numbered clause detection
                if (preg_match('/^(\d+\.?\d*\.?\d*)\s+\S/', $text)) {
                    $node->setAttribute(
                        'class',
                        trim($existing . ' corex-clause')
                    );
                    continue;
                }

                // Default paragraph
                $node->setAttribute(
                    'class',
                    trim($existing . ' corex-para')
                );
            }

            // Tables
            if ($tag === 'table') {
                $existing = $node->getAttribute('class');
                $node->setAttribute(
                    'class',
                    trim($existing . ' corex-table')
                );
            }

            // Lists
            if (in_array($tag, ['ul', 'ol'])) {
                $existing = $node->getAttribute('class');
                $node->setAttribute(
                    'class',
                    trim($existing . ' corex-list')
                );
            }
        }

        // Extract inner HTML
        $result = '';
        foreach ($container->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }

        return $result;
    }

    private function processClauseNumbering(string $html): string
    {
        // Wrap sub-clauses (1.1, 1.1.1) with indentation class
        $html = preg_replace_callback(
            '/<p([^>]*)>((\d+\.\d+\.\d+)\s+.+?)<\/p>/s',
            function($m) {
                $class = str_contains($m[1], 'class=')
                    ? str_replace(
                        'class="',
                        'class="corex-sub-sub-clause ',
                        $m[1]
                      )
                    : $m[1] . ' class="corex-sub-sub-clause"';
                return "<p{$class}>{$m[2]}</p>";
            },
            $html
        );

        $html = preg_replace_callback(
            '/<p([^>]*)>((\d+\.\d+)\s+.+?)<\/p>/s',
            function($m) {
                $class = str_contains($m[1], 'class=')
                    ? str_replace(
                        'class="',
                        'class="corex-sub-clause ',
                        $m[1]
                      )
                    : $m[1] . ' class="corex-sub-clause"';
                return "<p{$class}>{$m[2]}</p>";
            },
            $html
        );

        return $html;
    }

    private function processInputRows(string $html): string
    {
        // Detect paragraphs that are input rows
        // Pattern: "Label: ___" or "Label ___________"
        return preg_replace_callback(
            '/<p([^>]*)>(.+?)<\/p>/s',
            function($m) {
                $text = $m[2];
                $lower = strtolower(strip_tags($text));

                // Check if it matches input row patterns
                foreach ($this->inputRowPatterns as $pattern) {
                    if (str_contains($lower, $pattern)) {
                        // Has blank spaces or underscores after label
                        if (
                            str_contains($text, '___') ||
                            str_contains($text, '   ') ||
                            preg_match('/:\s*$/', strip_tags($text))
                        ) {
                            $class = str_contains($m[1], 'class=')
                                ? str_replace(
                                    'class="',
                                    'class="corex-input-row ',
                                    $m[1]
                                  )
                                : $m[1] . ' class="corex-input-row"';
                            return "<p{$class}>{$text}</p>";
                        }
                    }
                }

                return $m[0];
            },
            $html
        );
    }

    private function replaceSignatureSections(string $html): string
    {
        $lines = preg_split(
            '/(?=<(?:p|h[1-6]|div)[^>]*>)/i',
            $html
        );

        $output = [];
        $inSignatureSection = false;
        $signatureBlockInserted = false;

        foreach ($lines as $line) {
            $text = strtolower(strip_tags($line));

            $isSignatureTrigger = false;
            foreach ($this->signatureTriggers as $trigger) {
                if (str_contains($text, $trigger)) {
                    $isSignatureTrigger = true;
                    break;
                }
            }

            if ($isSignatureTrigger) {
                if (!$signatureBlockInserted) {
                    $output[] = $this->signatureBlockHtml();
                    $signatureBlockInserted = true;
                }
                $inSignatureSection = true;
                continue;
            }

            // Reset signature section on new numbered clause
            if (
                $inSignatureSection &&
                preg_match('/^\s*\d+\./', $text) &&
                strlen($text) > 10
            ) {
                $inSignatureSection = false;
                $signatureBlockInserted = false;
            }

            if (!$inSignatureSection) {
                $output[] = $line;
            }
        }

        return implode('', $output);
    }

    private function signatureBlockHtml(): string
    {
        return <<<HTML
<div class="corex-signature-block"
     data-signature-block="true">
    <div class="corex-signature-block-inner">
        <p class="corex-signature-label">
            SIGNATURES
        </p>
        <div class="corex-signature-grid">
            <div class="corex-signature-party">
                <div class="corex-signature-line"></div>
                <p class="corex-signature-name">
                    Lessor / Owner
                </p>
                <div class="corex-signature-line"></div>
                <p class="corex-signature-name">
                    Date
                </p>
            </div>
            <div class="corex-signature-party">
                <div class="corex-signature-line"></div>
                <p class="corex-signature-name">
                    Lessee / Tenant
                </p>
                <div class="corex-signature-line"></div>
                <p class="corex-signature-name">
                    Date
                </p>
            </div>
            <div class="corex-signature-party">
                <div class="corex-signature-line"></div>
                <p class="corex-signature-name">
                    Agent
                </p>
                <div class="corex-signature-line"></div>
                <p class="corex-signature-name">
                    Date
                </p>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
