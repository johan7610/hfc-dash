<?php

namespace App\Models\CommandCenter;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationEventType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key', 'pillar', 'group_label', 'label', 'description',
        'default_enabled', 'threshold_unit', 'default_threshold',
        'threshold_min', 'threshold_max',
        'supports_in_app', 'supports_email', 'supports_push',
        'is_adapter', 'adapter_column', 'sort_order',
    ];

    protected $casts = [
        'default_enabled' => 'boolean',
        'supports_in_app' => 'boolean',
        'supports_email'  => 'boolean',
        'supports_push'   => 'boolean',
        'is_adapter'      => 'boolean',
        'default_threshold' => 'integer',
        'threshold_min'   => 'integer',
        'threshold_max'   => 'integer',
    ];
}
