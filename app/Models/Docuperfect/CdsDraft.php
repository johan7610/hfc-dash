<?php

namespace App\Models\Docuperfect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CdsDraft extends Model
{
    use SoftDeletes;

    protected $table = 'cds_drafts';

    protected $fillable = [
        'user_id', 'agency_id', 'template_name',
        'cds_json', 'tags', 'mappings', 'tagged_html',
        'settings', 'source_template_id', 'status',
    ];

    protected $casts = [
        'cds_json' => 'array',
        'tags' => 'array',
        'mappings' => 'array',
        'settings' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function sourceTemplate()
    {
        return $this->belongsTo(Template::class, 'source_template_id');
    }
}
