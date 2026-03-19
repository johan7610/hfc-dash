<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contact_type_id', 'contact_source_id', 'created_by_user_id',
        'first_name', 'last_name', 'phone', 'email', 'notes',
        'birthday', 'id_number', 'address',
        'loaded_at', 'modified_at', 'last_contacted_at',
        'whatsapp_count', 'email_count',
        'bank_name', 'bank_account_name', 'bank_account_number',
        'bank_branch_name', 'bank_branch_code', 'bank_account_type',
    ];

    protected $casts = [
        'birthday'          => 'date',
        'loaded_at'         => 'datetime',
        'modified_at'       => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContactType::class, 'contact_type_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ContactSource::class, 'contact_source_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag')
                    ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function contactNotes(): HasMany
    {
        return $this->hasMany(ContactNote::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContactDocument::class)->latest();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ContactMatch::class)->latest();
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'contact_property')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }
}
