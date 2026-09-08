<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AT-392 Round 9 (item 5) — one line of the agent's expense/existing-debt
 * capture, agent can add as many as needed. SoftDeletes (non-negotiable #1)
 * — matches PayrollPayslipLine's precedent for a financial line-item ledger.
 */
class RentalApplicationExpenseItem extends Model
{
    use BelongsToAgency;
    use SoftDeletes;

    protected $fillable = [
        'agency_id', 'rental_application_assessment_id', 'description', 'amount', 'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(RentalApplicationAssessment::class, 'rental_application_assessment_id');
    }
}
