<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 3j — one SG document discovered for a property.
 *
 * Rows live in two states:
 *   - referenced (storage_path=null, is_saved=false) — agent has searched
 *     SG and we know this document exists for the parcel, but the TIF
 *     hasn't been downloaded to our storage yet.
 *   - saved (storage_path populated, is_saved=true) — TIF is on our disk.
 *
 * The UNIQUE constraint on (property_id, sg_document_number, sg_page_number)
 * means re-searching SG is idempotent — same documents map to same rows.
 */
final class PropertySgDocument extends Model
{
    use BelongsToAgency, SoftDeletes;

    public const TYPE_DIAGRAM      = 'DIAGRAM';
    public const TYPE_GENERAL_PLAN = 'GENERAL_PLAN';
    public const TYPE_SERVITUDE    = 'SERVITUDE';
    public const TYPE_TITLE_DEED   = 'TITLE_DEED';
    public const TYPE_OTHER        = 'OTHER';

    public const ALL_TYPES = [
        self::TYPE_DIAGRAM,
        self::TYPE_GENERAL_PLAN,
        self::TYPE_SERVITUDE,
        self::TYPE_TITLE_DEED,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'property_id',
        'agency_id',
        'sg_document_number',
        'sg_page_number',
        'sg_doc_type',
        'sg_source_url',
        'storage_path',
        'file_size_bytes',
        'mime_type',
        'sha256',
        'is_saved',
        'saved_at',
        'saved_by_user_id',
        'notes',
    ];

    protected $casts = [
        'sg_page_number'  => 'integer',
        'file_size_bytes' => 'integer',
        'is_saved'        => 'boolean',
        'saved_at'        => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function saver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by_user_id');
    }

    /**
     * Map an SG-site DOC TYPE string (e.g. "GENERAL PLAN") to our enum.
     */
    public static function normaliseDocType(?string $raw): string
    {
        $clean = mb_strtoupper(trim((string) $raw));
        $clean = str_replace([' ', '-'], '_', $clean);
        return in_array($clean, self::ALL_TYPES, true) ? $clean : self::TYPE_OTHER;
    }
}
