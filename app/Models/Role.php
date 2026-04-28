<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    /** @var Collection|null Cached roles for the current request */
    protected static ?Collection $cachedRoles = null;

    protected $fillable = [
        'name',
        'label',
        'description',
        'color',
        'sort_order',
        'agency_id',
        'oversight_scope',
    ];

    protected $casts = [
        'is_owner'       => 'boolean',
        'can_be_deleted'  => 'boolean',
        'sort_order'     => 'integer',
    ];

    // ── Relationships ──

    public function users()
    {
        return $this->hasMany(User::class, 'role', 'name');
    }

    public function permissions()
    {
        return $this->hasMany(RolePermission::class, 'role', 'name');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    // ── Scopes ──

    public function scopeForAgency($query, ?int $agencyId)
    {
        return $query->where(function ($q) use ($agencyId) {
            $q->whereNull('agency_id');
            if ($agencyId) {
                $q->orWhere('agency_id', $agencyId);
            }
        });
    }

    // ── Helpers ──

    public function isOwnerRole(): bool
    {
        return (bool) $this->is_owner;
    }

    /**
     * Get the single owner role.
     */
    public static function ownerRole(): ?self
    {
        return static::allRoles()->firstWhere('is_owner', true);
    }

    /**
     * Get all active roles (cached for the request).
     */
    public static function allRoles(): Collection
    {
        if (static::$cachedRoles === null) {
            static::$cachedRoles = static::orderBy('sort_order')->get();
        }

        return static::$cachedRoles;
    }

    /**
     * Clear the static cache (useful after role CRUD operations).
     */
    public static function clearCache(): void
    {
        static::$cachedRoles = null;
    }

    /**
     * Get all role names for validation rules.
     */
    public static function roleNames(): array
    {
        return static::allRoles()->pluck('name')->all();
    }
}
