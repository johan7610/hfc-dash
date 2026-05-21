<?php

declare(strict_types=1);

namespace App\Services\Docuperfect;

use App\Models\Docuperfect\DocumentClauseStrikethrough;
use App\Models\Docuperfect\DocumentCondition;
use App\Models\Docuperfect\SignatureTemplate;
use Illuminate\Support\Collection;

/**
 * E-Sign V3 (ES-9 / Phase 1B.5) — replace `~~~~MARKER~~~~` placeholder
 * tokens in a document's HTML body with styled, contextual block partials.
 *
 * Contexts:
 *   agent_preparation     — agent sees conditions + edit affordance hint
 *   agent_review          — same as preparation + diff highlights
 *   recipient_signing     — recipient sees conditions + "+ Add condition" button
 *   recipient_initialing  — only changed regions (handled by initialing view,
 *                           not this renderer)
 *   pdf_render            — flatten to numbered list, no interactive elements
 *
 * The renderer is intentionally HTML-string-in / HTML-string-out so it can
 * be threaded into any existing pipeline (signing view, wizard preview,
 * PDF flatten) by a single line insertion before the view emits the body.
 *
 * Spec: .ai/specs/esign-v3-complete-spec.md §7.5
 */
final class InsertableBlockRenderer
{
    public const CONTEXT_AGENT_PREPARATION    = 'agent_preparation';
    public const CONTEXT_AGENT_REVIEW         = 'agent_review';
    public const CONTEXT_RECIPIENT_SIGNING    = 'recipient_signing';
    public const CONTEXT_RECIPIENT_INITIALING = 'recipient_initialing';
    public const CONTEXT_PDF_RENDER           = 'pdf_render';

    /**
     * Replace every `~~~~MARKER~~~~` in $documentHtml with a styled block
     * rendered for the requested context.
     *
     * @param array<int, array<string, mixed>> $blocks
     *   The template's insertable_blocks metadata (id, purpose, label,
     *   position_marker, max_conditions, auto_number, locked, ...).
     */
    public function renderInDocument(
        string $documentHtml,
        SignatureTemplate $doc,
        array $blocks,
        string $context,
        ?string $signingToken = null
    ): string {
        if ($documentHtml === '' || empty($blocks)) {
            return $this->renderUnboundMarkers($documentHtml, $doc, $context, $signingToken);
        }

        // Group existing conditions and strikethroughs once for the whole pass.
        $conditionsByBlock = DocumentCondition::query()
            ->where('signature_template_id', $doc->id)
            ->whereNull('superseded_at')
            ->whereNull('deleted_at')
            ->orderBy('block_id')
            ->orderBy('condition_number')
            ->get()
            ->groupBy('block_id');

        $strikesByClauseRef = DocumentClauseStrikethrough::query()
            ->where('signature_template_id', $doc->id)
            ->whereIn('status', [
                DocumentClauseStrikethrough::STATUS_PROPOSED,
                DocumentClauseStrikethrough::STATUS_APPROVED,
            ])
            ->get()
            ->keyBy('clause_ref');

        $html = $documentHtml;
        foreach ($blocks as $block) {
            $marker = $block['position_marker'] ?? null;
            if (! is_string($marker) || $marker === '') {
                continue;
            }
            $rendered = $this->renderBlockPartial(
                $block,
                $conditionsByBlock->get($block['id'] ?? '', collect()),
                $doc,
                $context,
                $signingToken
            );
            $html = str_replace($marker, $rendered, $html);
        }

        // Catch markers in the body that aren't declared in the template's
        // insertable_blocks metadata (e.g. older templates pre-migration).
        $html = $this->renderUnboundMarkers($html, $doc, $context, $signingToken);

        // Apply strikethrough rendering pass for already-proposed overrides.
        $html = $this->applyStrikethroughs($html, $strikesByClauseRef->values());

        return $html;
    }

    /**
     * Apply struck-through CSS + inline annotation to clauses that have a
     * proposed/approved DocumentClauseStrikethrough. Idempotent — re-running
     * over an already-decorated string is a no-op.
     *
     * Match strategy: look for span/div elements carrying
     * `data-clause-ref="X.Y"` (set by the recipient-signing JS that wraps
     * numbered clauses). Skip elements already marked `data-strikethrough-applied`.
     */
    public function applyStrikethroughs(string $documentHtml, Collection $strikethroughs): string
    {
        if ($documentHtml === '' || $strikethroughs->isEmpty()) {
            return $documentHtml;
        }

        foreach ($strikethroughs as $strike) {
            $ref = (string) $strike->clause_ref;
            $replacementNumber = $strike->replacementCondition?->condition_number;
            $annotation = $replacementNumber
                ? sprintf(' <em class="override-annotation">[See Other Conditions #%d]</em>', $replacementNumber)
                : ' <em class="override-annotation">[See Other Conditions]</em>';

            $pattern = '/<(span|div|p|li)\b([^>]*\bdata-clause-ref="' . preg_quote($ref, '/') . '"[^>]*)>(.*?)<\/\1>/si';
            $documentHtml = preg_replace_callback($pattern, function ($m) use ($annotation) {
                $tag   = $m[1];
                $attrs = $m[2];
                $inner = $m[3];
                if (str_contains($attrs, 'data-strikethrough-applied')) {
                    return $m[0];
                }
                $attrs .= ' data-strikethrough-applied="1"';
                $styledInner = '<span style="text-decoration: line-through; color: #6b7280;">' . $inner . '</span>' . $annotation;
                return "<{$tag}{$attrs}>{$styledInner}</{$tag}>";
            }, $documentHtml);
        }

        return $documentHtml;
    }

    /**
     * Render a single block at its marker position in the document.
     *
     * @param array<string, mixed> $block
     * @param Collection<int, DocumentCondition> $conditions
     */
    private function renderBlockPartial(
        array $block,
        Collection $conditions,
        SignatureTemplate $doc,
        string $context,
        ?string $signingToken
    ): string {
        $blockId    = (string) ($block['id'] ?? '');
        $purpose    = (string) ($block['purpose'] ?? 'other_conditions');
        $label      = (string) ($block['label'] ?? $this->defaultLabelFor($purpose));
        $isLocked   = (bool)   ($block['locked'] ?? false);
        $autoNumber = (bool)   ($block['auto_number'] ?? ($purpose === 'other_conditions'));

        // Fallback: when there are no structured rows but
        // signature_templates.other_conditions_text is populated, render
        // that legacy text inline so pre-Phase-1B.5 documents still surface.
        $useLegacyTextFallback = $conditions->isEmpty()
            && $purpose === 'other_conditions'
            && trim((string) $doc->other_conditions_text) !== '';

        $itemsHtml = '';
        if ($conditions->isNotEmpty()) {
            $itemsHtml .= '<ol class="conditions-list">';
            foreach ($conditions as $c) {
                $itemsHtml .= $this->renderConditionRow($c, $context);
            }
            $itemsHtml .= '</ol>';
        } elseif ($useLegacyTextFallback) {
            $legacyLines = preg_split("/\r?\n/", (string) $doc->other_conditions_text);
            $itemsHtml .= '<div class="conditions-legacy-text" style="white-space:pre-wrap;">'
                . e(implode("\n", $legacyLines)) . '</div>';
        } else {
            $itemsHtml .= '<p class="no-conditions-yet" style="color:#6b7280; font-style:italic;">No conditions yet.</p>';
        }

        $purposeColors = [
            'other_conditions' => '#92400e',
            'included_items'   => '#047857',
            'excluded_items'   => '#be123c',
            'custom_named'     => '#475569',
        ];
        $color = $purposeColors[$purpose] ?? '#475569';

        $addButton = '';
        $canAdd = ! $isLocked && in_array($context, [
            self::CONTEXT_AGENT_PREPARATION,
            self::CONTEXT_AGENT_REVIEW,
            self::CONTEXT_RECIPIENT_SIGNING,
        ], true);
        if ($canAdd) {
            $tokenAttr = $signingToken ? ' data-signing-token="' . e($signingToken) . '"' : '';
            $addButton = '<button type="button" class="btn-add-condition" '
                . 'data-block-id="' . e($blockId) . '" '
                . 'data-block-purpose="' . e($purpose) . '" '
                . 'data-block-label="' . e($label) . '"'
                . $tokenAttr
                . ' style="margin-top:0.6rem; padding:0.35rem 0.75rem; border:1px dashed ' . $color . '; '
                . 'background:transparent; color:' . $color . '; border-radius:4px; cursor:pointer; font-size:0.85rem;">'
                . '+ Add condition</button>';
        }

        if ($context === self::CONTEXT_PDF_RENDER) {
            $addButton = '';
        }

        return '<div class="insertable-block" data-block-id="' . e($blockId) . '" '
            . 'data-purpose="' . e($purpose) . '" data-auto-number="' . ($autoNumber ? '1' : '0') . '" '
            . 'style="margin: 1rem 0; padding: 0.9rem 1rem; '
            . 'border-left: 3px solid ' . $color . '; '
            . 'background: color-mix(in srgb, ' . $color . ' 5%, transparent);">'
            . '<div class="block-header" style="margin-bottom: 0.6rem;">'
            . '<strong style="color:' . $color . '; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.78rem;">'
            . e($label)
            . '</strong>'
            . '</div>'
            . $itemsHtml
            . $addButton
            . '</div>';
    }

    private function renderConditionRow(DocumentCondition $c, string $context): string
    {
        $overrideBadge = '';
        if ($c->is_override && $c->overrides_clause_ref) {
            $overrideBadge = ' <span class="override-badge" '
                . 'style="display:inline-block; margin-left:0.4rem; padding:1px 6px; '
                . 'background:#fef3c7; color:#92400e; border-radius:3px; font-size:0.7rem;">'
                . 'Overrides clause ' . e((string) $c->overrides_clause_ref) . '</span>';
        }
        $lockedHint = $c->is_locked
            ? ' <span style="color:#6b7280; font-size:0.7rem; font-style:italic;">[locked]</span>'
            : '';

        return '<li class="condition-row" data-condition-id="' . $c->id . '" '
            . 'style="margin: 0.3rem 0; padding-left: 0.2rem;">'
            . nl2br(e($c->content))
            . $overrideBadge
            . $lockedHint
            . '</li>';
    }

    /**
     * Render any `~~~~MARKER~~~~` tokens that aren't bound to a block
     * record in template metadata. Best-effort fallback so a literal marker
     * never reaches the recipient. Catches OTHER_CONDITIONS, INCLUDED_ITEMS,
     * EXCLUDED_ITEMS, and CUSTOM:<label> forms.
     */
    private function renderUnboundMarkers(
        string $html,
        SignatureTemplate $doc,
        string $context,
        ?string $signingToken
    ): string {
        return preg_replace_callback(
            '/~{4,}([A-Z_]+(?::[^~]+)?)~{4,}/',
            function ($m) use ($doc, $context, $signingToken) {
                $token = $m[1];
                $synthBlock = $this->synthBlockFromToken($token);
                $conds = DocumentCondition::query()
                    ->where('signature_template_id', $doc->id)
                    ->where('block_id', $synthBlock['id'])
                    ->whereNull('superseded_at')
                    ->whereNull('deleted_at')
                    ->orderBy('condition_number')
                    ->get();
                return $this->renderBlockPartial($synthBlock, $conds, $doc, $context, $signingToken);
            },
            $html
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function synthBlockFromToken(string $token): array
    {
        if (str_starts_with($token, 'CUSTOM:')) {
            $label = trim(substr($token, 7));
            return [
                'id'              => 'custom_' . \Illuminate\Support\Str::slug($label !== '' ? $label : 'unnamed', '_'),
                'purpose'         => 'custom_named',
                'label'           => $label !== '' ? $label : 'Custom block',
                'custom_label'    => $label,
                'position_marker' => "~~~~CUSTOM:{$label}~~~~",
                'auto_number'     => false,
                'locked'          => false,
            ];
        }
        $purposeMap = [
            'OTHER_CONDITIONS' => 'other_conditions',
            'INCLUDED_ITEMS'   => 'included_items',
            'EXCLUDED_ITEMS'   => 'excluded_items',
        ];
        $purpose = $purposeMap[$token] ?? 'custom_named';
        return [
            'id'              => strtolower($token),
            'purpose'         => $purpose,
            'label'           => $this->defaultLabelFor($purpose),
            'position_marker' => "~~~~{$token}~~~~",
            'auto_number'     => $purpose === 'other_conditions',
            'locked'          => false,
        ];
    }

    private function defaultLabelFor(string $purpose): string
    {
        return match ($purpose) {
            'other_conditions' => 'Other Conditions',
            'included_items'   => 'Included Items',
            'excluded_items'   => 'Excluded Items',
            default            => 'Insertable Block',
        };
    }
}
