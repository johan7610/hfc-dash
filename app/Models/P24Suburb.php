<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class P24Suburb extends Model
{
    protected $table = 'p24_suburbs';

    protected $fillable = [
        'name',
        'slug',
        'p24_id',
        'region',
        'surrounding_ids',
        'confirmed',
    ];

    protected $casts = [
        'p24_id'          => 'integer',
        'surrounding_ids' => 'array',
        'confirmed'       => 'boolean',
    ];

    /**
     * Look up a suburb by name (case-insensitive) or slug.
     */
    public static function lookup(string $suburbName): ?self
    {
        $key = strtolower(trim($suburbName));
        $slug = str_replace(' ', '-', $key);

        return static::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [$key])
            ->first();
    }
}
