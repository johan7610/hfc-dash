<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * AT-392 — agency-configurable supporting-document checklist per employment
 * type. Spec: .ai/specs/rental-applications.md.
 *
 * Rule 17-safe: no agency row present means the V8 defaults below apply
 * IN MEMORY ONLY — never persisted as a side effect of merely reading them.
 * An agency that never opens the settings screen still gets a correct,
 * working default checklist.
 */
class RentalApplicationDocumentRequirement extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = ['agency_id', 'employment_type', 'document_type_id', 'sort_order'];

    /**
     * The V8 form's own printed checklist (template-97.blade.php lines 20-36),
     * expressed as document_types slugs. 'payslip' and 'financial_statements'
     * are the two new types added for this feature; everything else already
     * existed and is reused, not duplicated.
     */
    private const V8_DEFAULTS = [
        'permanently_employed' => ['payslip', 'bank_statement', 'ids', 'por'],
        'business_owner_personal_account' => ['bank_statement', 'ids', 'por'],
        'business_owner_business_account' => [
            'financial_statements', 'bank_statement', 'company_registration',
            'power_of_attorney', 'ids', 'por',
        ],
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * The effective checklist for one agency + employment type — saved rows
     * if the agency configured any, otherwise the V8 default. Returns
     * document_types rows (id, slug, label), never persists a default.
     */
    public static function checklistFor(int $agencyId, string $employmentType): Collection
    {
        // No withoutGlobalScope needed (CLAUDE.md Non-negotiable #7 forbids it
        // in request code): every caller passes the ACTING user's own
        // effective agency, which AgencyScope already resolves them to —
        // this is a normal, correctly-scoped read, not a cross-tenant one.
        $saved = static::query()
            ->where('agency_id', $agencyId)
            ->where('employment_type', $employmentType)
            ->orderBy('sort_order')
            ->with('documentType')
            ->get();

        if ($saved->isNotEmpty()) {
            return $saved->pluck('documentType')->filter()->values();
        }

        $slugs = self::V8_DEFAULTS[$employmentType] ?? [];

        return DocumentType::whereIn('slug', $slugs)->get()
            ->sortBy(fn ($dt) => array_search($dt->slug, $slugs))
            ->values();
    }
}
