<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalListingObservation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'portal_listing_id',
        'capture_id',
        'observed_at',
        'observed_fields_json',
        'changed_fields_json',
        'created_at',
    ];

    protected $casts = [
        'observed_at'          => 'datetime',
        'created_at'           => 'datetime',
        'observed_fields_json' => 'array',
        'changed_fields_json'  => 'array',
    ];

    public function portalListing()
    {
        return $this->belongsTo(PortalListing::class, 'portal_listing_id');
    }

    public function capture()
    {
        return $this->belongsTo(PortalCapture::class, 'capture_id');
    }
}
