<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectingPriceHistory extends Model
{
    protected $table = 'prospecting_price_history';

    protected $fillable = [
        'prospecting_listing_id',
        'old_price',
        'new_price',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(ProspectingListing::class, 'prospecting_listing_id');
    }
}
