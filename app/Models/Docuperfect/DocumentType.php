<?php

namespace App\Models\Docuperfect;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $table = 'docuperfect_document_types';

    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function templates()
    {
        return $this->hasMany(Template::class, 'document_type_id');
    }
}
