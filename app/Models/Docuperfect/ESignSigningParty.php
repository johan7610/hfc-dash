<?php

namespace App\Models\Docuperfect;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ESignSigningParty extends Model
{
    protected $table = 'esign_signing_parties';

    protected $fillable = [
        'flow_id',
        'contact_id',
        'role',
        'display_name',
        'id_number',
        'email',
        'phone',
        'signing_order',
        'status',
        'consented_at',
        'completed_at',
        'declined_at',
        'decline_reason',
        'proxy_for_party_id',
        'proxy_poa_reference',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'completed_at' => 'datetime',
        'declined_at' => 'datetime',
        'signing_order' => 'integer',
    ];

    public function setIdNumberAttribute($value)
    {
        $this->attributes['id_number'] = $value ? encrypt($value) : null;
    }

    public function getIdNumberAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function proxyFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'proxy_for_party_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
