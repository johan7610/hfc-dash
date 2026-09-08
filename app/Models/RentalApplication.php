<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AT-392 — Rental Application intake (Phase 1). Spec: .ai/specs/rental-applications.md
 *
 * Deliberately NOT routed through the e-sign wizard/SignatureTemplate machinery:
 * that pipeline structurally requires an agent-signing party (see the AT-332
 * investigation), which is overkill for a tenant intake form the agent never signs.
 */
class RentalApplication extends Model
{
    use BelongsToAgency, SoftDeletes;

    public const STATUSES = [
        'draft', 'sent', 'in_progress', 'returned', 'under_assessment', 'approved', 'declined', 'withdrawn',
    ];

    /**
     * AT-392 — Johan, QA1: "theres no way to mark application status to
     * what it is?" Split deliberately: draft/sent/in_progress/returned are
     * FACTS the system records (nothing typed, sent, or submitted — never
     * hand-settable, or the "status lies" defect this feature already fixed
     * once comes back). under_assessment/withdrawn are the agent's own
     * judgement calls once an application has actually been returned.
     *
     * AT-392 authoriser flow, 2026-09-08 — approved/declined REMOVED from
     * this list. Johan, verbatim: "only the auth can accept / reject / ask
     * for more information etc." An agent may work an application
     * (highlight, assess, request more info from the applicant) and submit
     * it to the authoriser, but the accept/reject decision itself belongs
     * exclusively to whoever is configured as an authoriser
     * (User::isRentalApplicationAuthoriser()) — enforced server-side on the
     * authorisation controller's own actions, not by hiding a button here.
     * Agreed with cc4 (who built this constant): stays additive, no new
     * status enum value, just this one narrowing.
     */
    public const AGENT_SETTABLE_STATUSES = [
        'under_assessment', 'withdrawn',
    ];

    /** Statuses at/after which a hand-set judgement call makes sense. */
    public const POST_RETURN_STATUSES = [
        'returned', 'under_assessment', 'approved', 'declined', 'withdrawn',
    ];

    public const EMPLOYMENT_TYPES = [
        'permanently_employed', 'business_owner_personal_account', 'business_owner_business_account',
    ];

    /**
     * ONE set of format rules for the V8 field list — shared by the agent-side
     * update (RentalApplicationController) and the public submit
     * (RentalApplicationSigningController), so the two never drift (BUILD_STANDARD
     * §6). Every field is `nullable` — nothing here may block a save — but a
     * MALFORMED value is rejected rather than allowed through to crash a
     * date/decimal cast at save() time (BUILD_STANDARD §2/§4).
     */
    /**
     * Every field validated as numeric/integer in fieldValidationRules()
     * below — kept as one list so sanitizeNumericInput() can never drift
     * out of sync with which fields actually need it.
     */
    public const NUMERIC_FIELDS = ['current_rental_amount', 'monthly_salary', 'adults', 'children'];

    /**
     * AT-392 — Johan, independent testing (cc5), RA-02: "a real person
     * types 15,000 because that is how South Africans write money, and
     * gets 'must be a number' with no explanation." Fixing the class, not
     * the instance: EVERY numeric field on both forms, not just the two
     * cc5 happened to hit. Strips thousand-separator commas, spaces
     * (either as a separator or from "R 15 000"), and a leading "R"
     * currency prefix before validation ever sees the value — a human
     * writing money the way South Africans actually write it must never
     * be told it's invalid.
     */
    /**
     * RA-02 follow-up, 2026-09-08 — cc5's re-test found the SAME defect on
     * fields this sweep never reached: the assessment panel's income/
     * expense fields (a different model, RentalApplicationAssessment), the
     * authoriser's approved_rental_amount, and the qualifying-formula
     * multiplier. `$fields` now defaults to `NUMERIC_FIELDS` (every
     * existing caller keeps working unchanged) but accepts an explicit
     * list so this one sanitizer serves every numeric money field on this
     * feature — no reason to duplicate the same three lines of string
     * cleanup in every controller that touches a rand amount.
     */
    public static function sanitizeNumericInput(array $input, ?array $fields = null): array
    {
        foreach ($fields ?? self::NUMERIC_FIELDS as $field) {
            if (isset($input[$field]) && is_string($input[$field]) && $input[$field] !== '') {
                $value = trim($input[$field]);
                $value = preg_replace('/^R\s*/i', '', $value);
                $value = str_replace([',', ' '], '', $value);
                $input[$field] = $value;
            }
        }

        return $input;
    }

    public static function fieldValidationRules(): array
    {
        return [
            'property_address_override' => ['nullable', 'string', 'max:500'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', 'string', 'max:100'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_id' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'current_residential_address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'cell' => ['nullable', 'string', 'max:50'],
            'work_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_cell' => ['nullable', 'string', 'max:50'],
            'emergency_contact_work' => ['nullable', 'string', 'max:50'],
            'current_landlord_name' => ['nullable', 'string', 'max:255'],
            'current_landlord_tel' => ['nullable', 'string', 'max:50'],
            'current_rental_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'current_rental_from' => ['nullable', 'date'],
            'current_rental_to' => ['nullable', 'date'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'employer_position' => ['nullable', 'string', 'max:255'],
            'employer_address' => ['nullable', 'string', 'max:2000'],
            'employer_tel' => ['nullable', 'string', 'max:50'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'employment_type' => ['nullable', 'in:' . implode(',', self::EMPLOYMENT_TYPES)],
            'occupation_date' => ['nullable', 'date'],
            'rental_terms' => ['nullable', 'string', 'max:255'],
            'special_conditions' => ['nullable', 'string', 'max:2000'],
            'adults' => ['nullable', 'integer', 'min:0', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }

    protected $fillable = [
        'agency_id', 'branch_id', 'contact_id', 'property_id', 'created_by_user_id',
        'status', 'delivery_mode', 'token', 'token_expires_at', 'submitted_at', 'submitted_for_approval_at', 'approved_rental_amount',
        'property_address_override',
        'full_name', 'id_number', 'marital_status', 'spouse_name', 'spouse_id', 'citizenship',
        'current_residential_address', 'email', 'cell', 'work_number',
        'emergency_contact_name', 'emergency_contact_cell', 'emergency_contact_work',
        'current_landlord_name', 'current_landlord_tel', 'current_rental_amount',
        'current_rental_from', 'current_rental_to',
        'employer_name', 'employer_position', 'employer_address', 'employer_tel',
        'monthly_salary', 'employment_type',
        'occupation_date', 'rental_terms', 'special_conditions', 'adults', 'children',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'submitted_for_approval_at' => 'datetime',
        'approved_rental_amount' => 'decimal:2',
        'current_rental_amount' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'current_rental_from' => 'date',
        'current_rental_to' => 'date',
        'occupation_date' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
    ];

    /**
     * AT-392 authoriser flow — the agent has handed this off; awaiting the
     * authoriser's decision. Deliberately NOT a status value (see
     * AGENT_SETTABLE_STATUSES comment) — status stays 'under_assessment',
     * this marker is what actually distinguishes the two states.
     */
    public function isPendingAuthorisation(): bool
    {
        return $this->status === 'under_assessment' && $this->submitted_for_approval_at !== null;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(RentalApplicationSignature::class);
    }

    /**
     * Supporting documents filed through the SHARED documents table
     * (source_type='rental_application', source_id=$this->id) — the same
     * convention every other filed document uses. No parallel documents table.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'source_id')->where('source_type', 'rental_application');
    }

    /** AT-392 — the status change trail, newest first. */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(RentalApplicationStatusHistory::class)->latest('created_at');
    }

    /** AT-392 authoriser flow — the fuller who/what/when/why/override audit trail, newest first. */
    public function auditLog(): HasMany
    {
        return $this->hasMany(RentalApplicationAuditLog::class)->latest('created_at');
    }

    public function declarationSignature(): ?RentalApplicationSignature
    {
        return $this->signatures->firstWhere('kind', 'declaration');
    }

    public function tpnConsentSignature(): ?RentalApplicationSignature
    {
        return $this->signatures->firstWhere('kind', 'tpn_consent');
    }

    public function isFullySigned(): bool
    {
        return $this->signatures()->whereIn('kind', ['declaration', 'tpn_consent'])->count() === 2;
    }

    /**
     * Johan, QA1 bug — "moans no email correctly, but adding and saving do
     * not persist." Root cause: two separate email values existed on one
     * screen. This field (`rental_applications.email`) is the ONLY one the
     * agent can see or edit (show.blade.php's "Email address" field), and
     * it was saving correctly all along. send()/sendInvite() instead always
     * read `contact->email` — a different column the agent has no way to
     * touch from this screen — so an agent who typed a correction here saw
     * it "not stick" because nothing that could actually send an email ever
     * consulted this field. Single choke point so send() and the mailer can
     * never independently drift back out of sync with each other.
     */
    public function recipientEmail(): ?string
    {
        return $this->email ?: $this->contact?->email;
    }

    /**
     * Johan, 2026-09-07 — "submitted docs are submitted. they can add, but
     * not replace or remove." This is the single source of truth for "has
     * this application already been submitted" — every submitted-lock check
     * (document replace/remove, the UI copy explaining why) reads through
     * here so the rule can't drift between call sites in a later refactor.
     * `submitted_at` is set exactly once, in submit()'s transaction, and
     * never cleared — equivalent to (and simpler than) re-checking the
     * status enum's "returned or later" set used elsewhere in this flow.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Johan, 2026-09-07 — "we always need proper crud... search / sort /
     * own / branch / agency levels. that should be the design standard...
     * from the word go." Own/branch/agency visibility, enforced at the
     * query layer (never by hiding a link). Mirrors
     * Docuperfect\Document::scopeVisibleTo() EXACTLY — same
     * PermissionService::getDataScope() resolution, same three branches —
     * so LIST and single-record access (see
     * AuthorizesRentalApplicationAccess) can never disagree. 'own' here is
     * the CREATING agent (created_by_user_id), this module's equivalent of
     * Document's owner_id.
     *
     * 2026-09-08 — index-screen scope FILTER (own/branch/agency toggle) added
     * an optional $requestedScope, clamped to the user's actual permitted
     * ceiling via clampScope() below — copied verbatim from
     * DealV2::scopeVisibleTo()/clampScope(), the established pattern for
     * exactly this ("a user must not be able to widen scope by editing the
     * URL beyond their permission"). $requestedScope defaults to null, which
     * preserves every existing caller's behaviour unchanged (ceiling only,
     * no narrowing) — this is additive, not a behaviour change for anyone
     * who doesn't pass the new argument.
     */
    public function scopeVisibleTo($query, User $user, ?string $requestedScope = null)
    {
        $scope = self::clampScope($requestedScope, \App\Services\PermissionService::getDataScope($user, 'rental_applications'));

        if ($scope === 'all') {
            return $query;
        }
        if ($scope === 'branch') {
            // 2026-09-08, found while proving the sort columns over real HTTP
            // (not by reading the blade): unqualified 'branch_id' throws
            // SQLSTATE 1052 "ambiguous" the moment this query is combined
            // with a LEFT JOIN to any table that ALSO has a branch_id column
            // — contacts, users, AND properties all do. That is every join
            // the index screen's own sort-by-Applicant/Agent/Property
            // columns already add. Pre-existing for sort=contact/property;
            // this task's new sort=agent hit the exact same class. Table-
            // qualified here, once, fixes all of them.
            return $query->where('rental_applications.branch_id', $user->effectiveBranchId());
        }
        if ($scope === 'own') {
            // Same reasoning — contacts also has created_by_user_id.
            return $query->whereIn('rental_applications.created_by_user_id', $user->dataIdentityIds());
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Narrow the requested scope to at most the permitted scope. Ranking
     * own(1) < branch(2) < all(3); "agency" (the UI's label for this
     * screen's toggle) is an alias for "all". Returns the MIN of requested
     * and permitted, so a branch manager asking for "agency" via the URL
     * still gets "branch", and any junk/missing value falls back to
     * permitted. Copied from DealV2::clampScope() — same ranking, same
     * fallback shape — deliberately not extracted into a shared trait as
     * part of this narrowly-scoped build; a future consolidation is a
     * separate decision.
     */
    public static function clampScope(?string $requested, ?string $permitted): string
    {
        $rank = ['own' => 1, 'branch' => 2, 'all' => 3, 'agency' => 3, 'company' => 3];
        $permitted = $permitted ?: 'own';
        $permRank = $rank[$permitted] ?? 1;
        if ($requested === null || ! isset($rank[$requested])) {
            return in_array($permitted, ['agency', 'company'], true) ? 'all' : $permitted;
        }
        $effRank = min($rank[$requested], $permRank);

        return array_search($effRank, ['own' => 1, 'branch' => 2, 'all' => 3], true) ?: 'own';
    }
}
