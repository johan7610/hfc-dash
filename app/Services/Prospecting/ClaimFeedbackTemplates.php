<?php

declare(strict_types=1);

namespace App\Services\Prospecting;

/**
 * MIC Phase G3 — quick-pick feedback templates for the claim feedback UI.
 *
 * Each template carries:
 *   - key             stable slug used by the UI + analytics
 *   - emoji           visual marker on the button
 *   - label           short button text
 *   - note            timestamped line prepended to claim.notes when picked
 *   - status          status to set on the claim (one of the
 *                     ProspectingClaim status values: pitched | scheduled |
 *                     interested | not_interested | listing | lost)
 *   - requires_input  optional flag — UI shows an extra input (e.g. "agency
 *                     name" for the already-listed template, "date" for the
 *                     follow-up template)
 *
 * The submit handler is the existing POST /corex/market-intelligence/
 * {listing}/feedback endpoint; the controller layer that consumes this
 * service prepends the note to claim.notes, sets feedback_at + status,
 * fires ClaimFeedbackRecorded.
 *
 * Spec: .ai/specs/mic-complete-spec.md §10.3.
 */
final class ClaimFeedbackTemplates
{
    /**
     * @return array<int, array{key:string, emoji:string, label:string, note:string, status:string, requires_input?:string}>
     */
    public static function getTemplates(): array
    {
        return [
            [
                'key'    => 'spoke_interested',
                'emoji'  => '📞',
                'label'  => 'Spoke — interested',
                'note'   => 'Spoke to owner. Interested in our mandate proposal.',
                'status' => 'interested',
                'requires_input' => 'follow_up_date',
            ],
            [
                'key'    => 'spoke_not_interested',
                'emoji'  => '📞',
                'label'  => 'Spoke — not interested',
                'note'   => 'Spoke to owner. Not interested in changing mandates.',
                'status' => 'not_interested',
            ],
            [
                'key'    => 'no_answer',
                'emoji'  => '📵',
                'label'  => 'Could not reach',
                'note'   => 'Tried to reach owner. Left a message.',
                'status' => 'pitched',
            ],
            [
                'key'    => 'wrong_contact',
                'emoji'  => '❌',
                'label'  => 'Wrong number / address',
                'note'   => 'Contact details on listing are wrong. Updated record where possible.',
                'status' => 'lost',
            ],
            [
                'key'    => 'already_listed',
                'emoji'  => '🏷️',
                'label'  => 'Already with another agency',
                'note'   => 'Owner already has the property listed with another agency.',
                'status' => 'lost',
                'requires_input' => 'competitor_name',
            ],
            [
                'key'    => 'custom',
                'emoji'  => '✏️',
                'label'  => 'Custom note',
                'note'   => '',
                'status' => 'pitched',
                'requires_input' => 'custom_note',
            ],
        ];
    }

    /**
     * Look up a single template by key. Returns null if no template
     * matches — the controller should reject the request in that case.
     */
    public static function find(string $key): ?array
    {
        foreach (self::getTemplates() as $tpl) {
            if ($tpl['key'] === $key) return $tpl;
        }
        return null;
    }

    /**
     * Build the timestamped note line for a template + optional user input.
     * Used by the feedback controller to prepend onto claim.notes.
     */
    public static function buildNoteLine(string $key, ?string $userInput, string $agentName): ?string
    {
        $tpl = self::find($key);
        if ($tpl === null) return null;

        $body = $tpl['note'];
        $extra = trim((string) $userInput);
        if ($extra !== '') {
            $body = match ($tpl['requires_input'] ?? null) {
                'follow_up_date'  => $body . ' Follow up on ' . $extra . '.',
                'competitor_name' => $body . ' Mandate is with ' . $extra . '.',
                'custom_note'     => $extra,
                default           => $body . ' ' . $extra,
            };
        }

        return sprintf('[%s · %s] %s', now()->format('j M Y H:i'), $agentName, $body);
    }
}
