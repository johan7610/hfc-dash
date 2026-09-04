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
        'sent', 'in_progress', 'returned', 'under_assessment', 'approved', 'declined', 'withdrawn',
    ];

    public const EMPLOYMENT_TYPES = [
        'permanently_employed', 'business_owner_personal_account', 'business_owner_business_account',
    ];

    protected $fillable = [
        'agency_id', 'branch_id', 'contact_id', 'property_id', 'created_by_user_id',
        'status', 'delivery_mode', 'token', 'token_expires_at', 'submitted_at',
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
        'current_rental_amount' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'current_rental_from' => 'date',
        'current_rental_to' => 'date',
        'occupation_date' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
    ];

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
}
