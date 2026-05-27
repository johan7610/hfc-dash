<?php

namespace App\Services\Docuperfect;

/**
 * Promotes inline web-template signature blocks to the signing engine's
 * signable convention.
 *
 * The engine selects signable surfaces via the selector
 * [data-marker-party][data-marker-type="signature"] in THREE places:
 *   - resources/views/docuperfect/signatures/sign.blade.php:727 (agent)
 *   - resources/views/docuperfect/signatures/external/sign.blade.php:1423 (signer)
 *   - SignatureController::embedSignaturesIntoHtml:1484 (server PDF embed)
 *
 * Templates built on the shared signature-block partial already emit this.
 * Hand-rolled inline templates put data-marker-party on a .signature-col /
 * .signature-section WRAPPER but never emit data-marker-type — so the engine
 * finds zero surfaces and the document opens but can never be signed
 * (audit BL-5 / BL-6).
 *
 * This normaliser runs at the signing-view / embed chokepoints (controllers,
 * never the 8 template files) and additively stamps data-marker-type="signature"
 * onto the inner signature LINE element (so the visible label sibling survives —
 * the engine wipes innerHTML of the matched element). It is:
 *   - idempotent: if any data-marker-type="signature" already exists the HTML
 *     is returned untouched, so partial-based templates (letting-mandate-v5,
 *     CDS templates) and already-embedded signatures are never disturbed;
 *   - fidelity-safe: only adds attributes the browser ignores visually — no
 *     legal text, layout, or pixel change;
 *   - fail-open: any parse error returns the original HTML.
 *
 * Mirrors the proven DOMDocument round-trip used by
 * SignatureController::embedSignaturesIntoHtml on this same merged_html.
 */
class SignatureSurfaceNormalizer
{
    public static function normalize(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return $html;
        }

        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML(
                '<?xml encoding="utf-8"?>' . $html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR
            );
            $xpath = new \DOMXPath($dom);

            $clsContains = fn (string $c) =>
                "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')";

            // Apply Variant A/B within a scope (null = whole document).
            // Pack-aware: a merged pack has one .corex-document-wrapper per
            // template. The old whole-document early-return fired the moment
            // ANY segment was already conventional, leaving non-conventional
            // LATER segments permanently unsignable. Run the idempotent
            // check + Variant A/B PER scope so every segment is normalised
            // independently. Single docs (0/1 wrapper) keep exact prior
            // behaviour (whole-document scope).
            $applyVariants = function (?\DOMNode $scope) use ($xpath, $clsContains) {
                $rel = $scope ? './/' : '//';

                // Idempotent within scope: already conventional — skip.
                if ($xpath->query($rel . '*[@data-marker-type="signature"]', $scope)->length > 0) {
                    return false;
                }

                $changed = false;

                // Variant A — .signature-col[data-marker-party] > .signature-line
                // (marketing-permission-v6, sales-mandatory-disclosure,
                //  letting-marketing-permission-v7, commercial-lease-agreement-v5,
                //  lease-agreement-popi-v8, rental-application-v8)
                $cols = $xpath->query(
                    $rel . '*[' . $clsContains('signature-col') . '][@data-marker-party]',
                    $scope
                );
                foreach ($cols as $col) {
                    /** @var \DOMElement $col */
                    $lines = $xpath->query('.//*[' . $clsContains('signature-line') . ']', $col);
                    if ($lines->length === 0) {
                        continue;
                    }
                    /** @var \DOMElement $line */
                    $line = $lines->item(0);
                    $line->setAttribute('data-marker-party', $col->getAttribute('data-marker-party'));
                    if ($col->hasAttribute('data-marker-index')) {
                        $line->setAttribute('data-marker-index', $col->getAttribute('data-marker-index'));
                    }
                    $line->setAttribute('data-marker-type', 'signature');
                    $changed = true;
                }

                // Variant B — .signature-section[data-marker-party] with NO
                // .signature-col; the signature blank is an inner
                // span.field[data-field$="signature"] (letting-mandatory-disclosure-v7)
                $sections = $xpath->query(
                    $rel . '*[' . $clsContains('signature-section') . '][@data-marker-party]',
                    $scope
                );
                foreach ($sections as $section) {
                    /** @var \DOMElement $section */
                    if ($xpath->query('.//*[' . $clsContains('signature-col') . ']', $section)->length > 0) {
                        continue; // already covered as Variant A
                    }
                    $spans = $xpath->query('.//span[' . $clsContains('field') . '][@data-field]', $section);
                    $target = null;
                    foreach ($spans as $span) {
                        if (preg_match('/signature$/i', $span->getAttribute('data-field'))) {
                            $target = $span;
                            break;
                        }
                    }
                    if ($target === null) {
                        continue;
                    }
                    /** @var \DOMElement $target */
                    $target->setAttribute('data-marker-party', $section->getAttribute('data-marker-party'));
                    if ($section->hasAttribute('data-marker-index')) {
                        $target->setAttribute('data-marker-index', $section->getAttribute('data-marker-index'));
                    }
                    $target->setAttribute('data-marker-type', 'signature');
                    $changed = true;
                }

                return $changed;
            };

            $wrappers = $xpath->query('//*[' . $clsContains('corex-document-wrapper') . ']');
            $changed = false;
            if ($wrappers->length >= 2) {
                // Merged pack — normalise each template segment independently.
                foreach ($wrappers as $wrap) {
                    if ($applyVariants($wrap)) {
                        $changed = true;
                    }
                }
            } else {
                // Single document (0/1 wrapper) — original whole-doc behaviour.
                $changed = $applyVariants(null);
            }

            if (! $changed) {
                return $html;
            }

            $result = $dom->saveHTML();
            $result = preg_replace('/^<\?xml encoding="utf-8"\?>/', '', $result);

            return trim($result);
        } catch (\Throwable $e) {
            \Log::warning('SIGNATURE_SURFACE_NORMALIZE_FAILED', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return $html;
        }
    }
}
