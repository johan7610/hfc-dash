<?php

namespace App\Models\CommandCenter;

use Illuminate\Database\Eloquent\Model;

class ReminderDefault extends Model
{
    protected $table = 'command_reminder_defaults';

    protected $fillable = [
        'event_category', 'reminder_offsets', 'escalation_enabled',
        'escalation_delay', 'escalation_to', 'agency_id',
    ];

    protected $casts = [
        'reminder_offsets'   => 'array',
        'escalation_enabled' => 'boolean',
    ];
}
