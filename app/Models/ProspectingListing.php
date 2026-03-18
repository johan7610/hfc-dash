<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProspectingListing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agency_id',
        'captured_by_user_id',
        'portal_source',
        'portal_ref',
        'portal_url',
        'address',
        'suburb',
        'district',
        'price',
        'bedrooms',
        'bathrooms',
        'garages',
        'property_size_m2',
        'erf_size_m2',
        'property_type',
        'agent_name',
        'agency_name',
        'thumbnail_path',
        'first_seen_at',
        'last_seen_at',
        'price_changed_at',
        'is_active',
    ];

    protected $casts = [
        'price'            => 'integer',
        'is_active'        => 'boolean',
        'first_seen_at'    => 'datetime',
        'last_seen_at'     => 'datetime',
        'price_changed_at' => 'datetime',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function capturedBy()
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function priceHistory()
    {
        return $this->hasMany(ProspectingPriceHistory::class);
    }
}
