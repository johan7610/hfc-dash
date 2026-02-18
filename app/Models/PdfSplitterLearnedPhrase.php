<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfSplitterLearnedPhrase extends Model
{
    protected $table = 'pdf_splitter_learned_phrases';

    protected $fillable = [
        'phrase',
        'bucket',
        'hits',
        'weight',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
