<?php

namespace App\Models\CommandCenter;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNotificationPreference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'notification_event_type_id',
        'enabled', 'threshold',
        'channel_in_app', 'channel_email', 'channel_push',
    ];

    protected $casts = [
        'enabled'        => 'boolean',
        'channel_in_app' => 'boolean',
        'channel_email'  => 'boolean',
        'channel_push'   => 'boolean',
        'threshold'      => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(NotificationEventType::class, 'notification_event_type_id');
    }
}
