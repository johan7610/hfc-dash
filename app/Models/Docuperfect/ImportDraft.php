<?php

namespace App\Models\Docuperfect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportDraft extends Model
{
    use SoftDeletes;

    protected $table = 'docuperfect_import_drafts';

    protected $fillable = [
        'user_id',
        'filename',
        'html',
        'fields_json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
