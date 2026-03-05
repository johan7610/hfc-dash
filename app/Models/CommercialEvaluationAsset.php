<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommercialEvaluationAsset extends Model
{
    use SoftDeletes;


    protected $table = 'commercial_evaluation_assets';

    protected $fillable = [
        'commercial_evaluation_id',
        'category',
        'description',
        'quantity',
        'estimated_value',
        'notes',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'estimated_value' => 'integer',
    ];

    public function evaluation()
    {
        return $this->belongsTo(CommercialEvaluation::class, 'commercial_evaluation_id');
    }
}
