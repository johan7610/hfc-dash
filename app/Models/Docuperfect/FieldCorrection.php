<?php

namespace App\Models\Docuperfect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldCorrection extends Model
{
    use SoftDeletes;

    protected $table = 'docuperfect_field_corrections';

    protected $fillable = [
        'context',
        'claude_suggested_key',
        'claude_suggested_label',
        'user_corrected_key',
        'user_corrected_label',
        'correction_reason',
        'document_type',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
