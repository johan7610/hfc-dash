<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AT-392 Phase 2 — the agent's own affordability capture for a rental
 * application, one row per application (see the migration for the "why").
 * Autosaved from the review screen's right panel; every field nullable so
 * a partially-filled assessment never blocks anything.
 */
class RentalApplicationAssessment extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'rental_application_id',
        'monthly_income', 'other_monthly_income', 'monthly_expenses', 'notes',
        'updated_by_user_id',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'other_monthly_income' => 'decimal:2',
        'monthly_expenses' => 'decimal:2',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * The qualifying calculation — SUGGESTIVE, NEVER A RULE. Johan, verbatim:
     * "The marking is only suggestive to the agent to spot. not rule of
     * thumb." This method never blocks, never auto-declines, never writes a
     * decision anywhere — it only returns numbers and a label for the agent
     * to read. Returns null fields when there isn't enough input to compute
     * anything (never a misleading zero).
     */
    public function qualifyingResult(float $multiplier): array
    {
        $totalIncome = null;
        if ($this->monthly_income !== null || $this->other_monthly_income !== null) {
            $totalIncome = (float) ($this->monthly_income ?? 0) + (float) ($this->other_monthly_income ?? 0);
        }

        $netIncome = $totalIncome === null ? null : $totalIncome - (float) ($this->monthly_expenses ?? 0);

        $rentalApplication = $this->rentalApplication;
        $rent = $rentalApplication?->current_rental_amount !== null
            ? (float) $rentalApplication->current_rental_amount
            : null;

        $requiredIncome = $rent !== null ? round($rent * $multiplier, 2) : null;

        $meetsThreshold = ($totalIncome !== null && $requiredIncome !== null)
            ? $totalIncome >= $requiredIncome
            : null;

        return [
            'total_income' => $totalIncome,
            'net_income' => $netIncome,
            'rent' => $rent,
            'multiplier' => $multiplier,
            'required_income' => $requiredIncome,
            'meets_threshold' => $meetsThreshold,
            // 'sufficient' | 'insufficient' | 'incomplete' — incomplete when
            // there isn't enough input to say anything at all.
            'label' => $meetsThreshold === null ? 'incomplete' : ($meetsThreshold ? 'sufficient' : 'insufficient'),
        ];
    }

    public function rentalApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class);
    }
}
