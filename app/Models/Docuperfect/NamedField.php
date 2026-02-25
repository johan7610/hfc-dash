<?php

namespace App\Models\Docuperfect;

use Illuminate\Database\Eloquent\Model;

class NamedField extends Model
{
    protected $table = 'docuperfect_named_fields';

    protected $fillable = [
        'name',
        'field_type',
        'default_options',
        'sort_order',
    ];

    protected $casts = [
        'default_options' => 'array',
        'sort_order' => 'integer',
    ];
}
