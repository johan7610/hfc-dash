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
 * honestly populate: the applicant's name and the agency's name. No
 * property, no rent figure, no "reasons" — decline doesn't collect any of
 * those the way approve's amount does.
 */
class RentalApplicationDeclineEmailSetting extends Model
{
    use BelongsToAgency;

    protected $fillable = ['agency_id', 'subject', 'body'];

    /**
     * Suggested default — Johan asked for "plain, respectful and not cold."
     * Deliberately does NOT include "how to improve your history" guidance
     * — he was explicit that's still an open idea, not settled, not mine to
     * write.
     */
    public const DEFAULT_SUBJECT = 'Your rental application — {{agency_name}}';

    public const DEFAULT_BODY = <<<'TEXT'
Dear {{applicant_name}},

Thank you for your interest in renting through {{agency_name}}, and for taking the time to complete your application.

After reviewing your application, we're sorry to let you know that we're not able to proceed with it on this occasion.

This isn't necessarily a reflection of you personally — rental applications are assessed against a number of factors, and sometimes a good match on paper still isn't the right fit for a specific property.

If you have any questions about this decision, please don't hesitate to contact us.

We wish you the best in finding a home that's right for you.

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

    /** Only fields this email can ALWAYS honestly populate — see class docblock. */
    public static function render(string $template, string $applicantName, string $agencyName): string
    {
        return strtr($template, [
            '{{applicant_name}}' => $applicantName,
            '{{agency_name}}' => $agencyName,
        ]);
    }
}
