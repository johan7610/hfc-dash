<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 3j — 24h cache of SG search responses.
 *
 * Keyed by SHA-256 of the normalised query so spelling variations that
 * normalise the same hit the same row. parsed_documents_json is the
 * canonical read shape; response_body is kept only for diagnosis when
 * the parser breaks (SG HTML is hand-rolled JSP, it WILL drift).
 *
 * No agency_id — SG search responses are public-domain data, agencies
 * naturally share the cache.
 */
final class SgSearchCache extends Model
{
    protected $table = 'sg_search_cache';

    protected $fillable = [
        'query_hash',
        'province',
        'rural_urban',
        'town',
        'parcel_number',
        'portion',
        'farm_name',
        'response_body',
        'parsed_documents_json',
        'fetched_at',
        'expires_at',
    ];

    protected $casts = [
        'parsed_documents_json' => 'array',
        'fetched_at'            => 'datetime',
        'expires_at'            => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
