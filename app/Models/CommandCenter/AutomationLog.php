<?php

namespace App\Models\CommandCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    public $timestamps = false;

    protected $table = 'automation_log';

    protected $fillable = [
        'rule_id', 'trigger_model_type', 'trigger_model_id',
        'action_type', 'action_result_type', 'action_result_id',
        'executed_at', 'success', 'error_message',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'success'     => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
