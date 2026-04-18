<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes, BelongsToAgency;

    protected $fillable = [
        'name',
        'code',
        'agency_id',
        'trading_name',
        'tagline',
        'address',
        'phone',
        'phone_label',
        'phone_secondary',
        'phone_secondary_label',
        'fax',
        'email',
        'reg_no',
        'vat_no',
        'ffc_no',
        'fic_no',
        'logo_path',
        'p24_agency_id',
    ];

    /**
     * Returns contact detail value — branch value if set,
     * otherwise falls back to Agency value.
     */
    public function contactDetail(string $field): ?string
    {
        return $this->{$field} ?? $this->agency->{$field} ?? null;
    }

    /**
     * Resolve the Property24 agency ID for this branch: branch override,
     * else parent agency's default. Null = neither configured.
     */
    public function resolveP24AgencyId(): ?string
    {
        if (!empty($this->p24_agency_id)) {
            return (string) $this->p24_agency_id;
        }
        $parent = $this->agency?->p24_agency_id;
        return $parent !== null && $parent !== '' ? (string) $parent : null;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
