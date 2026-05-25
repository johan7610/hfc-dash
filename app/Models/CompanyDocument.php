<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Phase 9c-3 — admin-managed legal/compliance documents.
 *
 * One row per agency × document_type. Public URL is /legal/{public_token}
 * (no auth, no agency middleware). Token pattern mirrors
 * `presentation_snapshot_links`.
 */
final class CompanyDocument extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    protected $table = 'company_documents';

    /** Curated document-type slugs surfaced in the admin "Create new" picker. */
    public const TYPES = [
        'privacy_policy'        => 'Privacy Policy',
        'terms_of_service'      => 'Terms of Service',
        'complaints_procedure'  => 'Complaints Procedure',
        'aml_statement'         => 'AML / FICA Statement',
        'code_of_conduct'       => 'Code of Conduct',
        'popia_consent_text'    => 'POPIA Consent Text',
    ];

    protected $fillable = [
        'agency_id',
        'document_type',
        'title',
        'content',
        'content_format',
        'public_token',
        'is_published',
        'published_at',
        'last_updated_by_user_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Auto-generate the public token on create; never regenerate on update
        // — agents may have shared the link already.
        static::creating(function (self $doc): void {
            if (empty($doc->public_token)) {
                $doc->public_token = self::generateToken();
            }
        });
    }

    public static function generateToken(): string
    {
        // 48 char URL-safe random — collision-free at any scale we care about.
        do {
            $token = Str::random(48);
        } while (self::where('public_token', $token)->exists());
        return $token;
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function scopeForAgency(Builder $q, int $agencyId): Builder
    {
        return $q->where('agency_id', $agencyId);
    }

    public function scopeOfType(Builder $q, string $slug): Builder
    {
        return $q->where('document_type', $slug);
    }

    public function publicUrl(): string
    {
        return route('public.company-document', ['token' => $this->public_token]);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? $this->document_type;
    }
}
