<?php

namespace App\Models\CommandCenter;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarReminderLog extends Model
{
    public $timestamps = false;

    protected $table = 'calendar_reminders_log';

    protected $fillable = [
        'calendar_event_id', 'user_id', 'channel', 'offset_minutes',
        'sent_at', 'read_at', 'actioned_at', 'escalated',
    ];

    protected $casts = [
        'sent_at'     => 'datetime',
        'read_at'     => 'datetime',
        'actioned_at' => 'datetime',
        'escalated'   => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
