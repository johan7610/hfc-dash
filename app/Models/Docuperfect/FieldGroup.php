<?php

namespace App\Models\Docuperfect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldGroup extends Model
{
    use SoftDeletes;

    protected $table = 'docuperfect_field_groups';

    protected $fillable = [
        'agency_id',
        'created_by',
        'name',
        'description',
        'fields',
        'layout',
        'sort_order',
        'is_global',
    ];

    protected $casts = [
        'fields' => 'array',
        'sort_order' => 'integer',
        'is_global' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(\App\Models\Agency::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
