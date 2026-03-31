<?php

namespace App\Models\CommandCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentExpectation extends Model
{
    use SoftDeletes;

    protected $table = 'command_document_expectations';

    protected $fillable = [
        'property_type', 'document_type_id', 'required', 'due_offset_hours',
        'label', 'sort_order', 'agency_id',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];
}
