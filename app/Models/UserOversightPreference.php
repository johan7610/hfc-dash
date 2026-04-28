<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOversightPreference extends Model
{
    use BelongsToAgency, SoftDeletes;

    public const CATEGORIES = [
        'ignored_notifications',
        'deals_near_expiry',
        'expiring_mandates',
        'stale_listings',
        'overdue_tasks',
        'expiring_ffcs',
        'stale_leads',
    ];

    public const DEFAULTS = [
        'ignored_notifications' => ['threshold_hours' => 24,    'notify_channel' => 'in_app'],
        'deals_near_expiry'     => ['threshold_hours' => 168,   'notify_channel' => 'both'],
        'expiring_mandates'     => ['threshold_hours' => 336,   'notify_channel' => 'both'],
        'stale_listings'        => ['threshold_hours' => 336,   'notify_channel' => 'in_app'],
        'overdue_tasks'         => ['threshold_hours' => 0,     'notify_channel' => 'in_app'],
        'expiring_ffcs'         => ['threshold_hours' => 720,   'notify_channel' => 'both'],
        'stale_leads'           => ['threshold_hours' => 168,   'notify_channel' => 'in_app'],
    ];

    protected $fillable = [
        'agency_id', 'user_id', 'category', 'enabled', 'threshold_hours', 'notify_channel',
    ];

    protected $casts = [
        'enabled' => 'bool',
        'threshold_hours' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
