<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfSplitterFeedback extends Model
{
    protected $table = 'pdf_splitter_feedback';

    protected $fillable = [
        'user_id',
        'manifest_id',
        'base',
        'page',
        'auto_label',
        'final_label',
        'snippet',
        'page_scores',
    ];

    protected $casts = [
        'page_scores' => 'array',
    ];
}
