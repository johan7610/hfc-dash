<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

/**
 * AT-392 authoriser flow — Johan: "each agency will want their own wording
 * on declined." Same Rule-17-safe shape as RentalApplicationQualifyingSetting
 * — forAgency() never writes on read, only returns the suggested default
 * text until an agency actually saves their own wording.
 *
 * Merge fields are deliberately limited to what a decline email can ALWAYS
 * honestly populate: the applicant's name, the agency's name, and
 * (optionally, 2026-09-08) which property the application was for — an
 * applicant may have several applications running at once and already
 * knows what they applied for, so this isn't internal information. No
 * rent figure, no "reasons" — decline doesn't collect those the way
 * approve's amount does.
 */
class RentalApplicationDeclineEmailSetting extends Model
{
    use BelongsToAgency;

    protected $fillable = ['agency_id', 'subject', 'body'];

    /**
     * Suggested default — Johan asked for "plain, respectful and not cold."
     * Deliberately does NOT include "how to improve your history" guidance
     * — he was explicit that's still an open idea, not settled, not mine to
     * write. Coordinator-approved wording, 2026-09-08 — two rounds of
     * review: dropped "if you have any questions about this decision" (it
     * invites arguing the decision) and "this isn't a reflection of you
     * personally" (it plants the very doubt it's trying to avoid) — kept
     * only "we'd welcome an application from you again in future."
     */
    public const DEFAULT_SUBJECT = 'Your rental application — {{agency_name}}';

    public const DEFAULT_BODY = <<<'TEXT'
Dear {{applicant_name}},

{{property_reference}}Thank you for applying to rent through {{agency_name}}, and for the time you put into your application.

We're sorry to let you know that we won't be able to proceed with it on this occasion.

We'd welcome an application from you again in future, should a suitable property come up.

We wish you well in your search for a home.

Kind regards,
{{agency_name}}
TEXT;

    /** @return array{subject: string, body: string} */
    public static function forAgency(?int $agencyId): array
    {
        $row = $agencyId ? static::where('agency_id', $agencyId)->first() : null;

        return [
            'subject' => $row?->subject ?: self::DEFAULT_SUBJECT,
            'body' => $row?->body ?: self::DEFAULT_BODY,
        ];
    }

    /**
     * Only fields this email can ALWAYS honestly populate — see class
     * docblock. $propertyReference is OPTIONAL (2026-09-08 — an applicant
     * may have several applications running at once and needs to know
     * which one this is about; that's information they already have, not
     * an internal detail). Rendered as a self-contained "Re:" line so the
     * default body reads correctly whether or not it's supplied — when
     * null/empty the placeholder resolves to nothing, not a dangling label
     * or blank line, and an agency's own custom wording is free to omit
     * the placeholder entirely.
     */
    public static function render(string $template, string $applicantName, string $agencyName, ?string $propertyReference = null): string
    {
        return strtr($template, [
            '{{applicant_name}}' => $applicantName,
            '{{agency_name}}' => $agencyName,
            '{{property_reference}}' => $propertyReference ? "Re: {$propertyReference}\n\n" : '',
        ]);
    }
}
