<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AT-392 Phase 2 — the agent's own affordability capture for a rental
 * application, one row per application (see the migration for the "why").
 * Autosaved from the review screen's right panel; every field nullable so
 * a partially-filled assessment never blocks anything.
 *
 * Round 9 (item 5) — monthly_income/other_monthly_income/monthly_expenses
 * were fixed columns; replaced with incomeItems()/expenseItems(), an
 * agent-growable list of lines each (Johan: "filling the last row auto-adds
 * a fresh empty one"). See
 * 2026_09_08_180000_create_rental_application_income_expense_items_tables.php
 * for the data migration that preserved existing captured amounts.
 */
class RentalApplicationAssessment extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'rental_application_id', 'notes', 'statement_months', 'updated_by_user_id',
    ];

    protected $casts = [
        'statement_months' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function incomeItems(): HasMany
    {
        return $this->hasMany(RentalApplicationIncomeItem::class, 'rental_application_assessment_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(RentalApplicationExpenseItem::class, 'rental_application_assessment_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    /**
     * THE RULE, stated as the law states it — Johan, from his own reading:
     * "the law states you may not spend more than 30% of your gross
     * income on rentals... its not 3.5 or what you created it as of nett
     * disposable income. its of the gross income." Rent must not exceed
     * $maxRentPercent% of GROSS income. Not a multiplier of rent (the same
     * arithmetic wearing a disguise — nobody could check a "3.5x" figure
     * against the actual legal guideline at a glance), not net-of-expenses
     * (confirmed before this change: the OLD version already tested
     * income-before-expenses against the threshold, matching the law's own
     * "gross" basis by coincidence of arithmetic direction, not by
     * design — this version makes that basis explicit and correct).
     *
     * SUGGESTIVE, NEVER A RULE. Johan, verbatim: "The marking is only
     * suggestive to the agent to spot. not rule of thumb." This method
     * never blocks, never auto-declines, never writes a decision anywhere
     * — it only returns numbers and a label for the agent to read. Returns
     * null fields when there isn't enough input to compute anything
     * (never a misleading zero).
     *
     * `net_income` is still computed and returned — Johan's explicit call,
     * kept as genuinely useful context an agent typed in themselves (what's
     * left after existing debts/expenses), but it plays NO part in
     * `meets_threshold`. Any screen that displays it must label it
     * unmistakably as reference-only, separated from the pass/fail badge —
     * the OLD screen showed it unlabelled next to the badge, which is
     * exactly what made an agent reasonably assume it was being tested.
     */
    public function qualifyingResult(float $maxRentPercent): array
    {
        $incomeItems = $this->incomeItems;
        $grossIncome = $incomeItems->isEmpty() ? null : (float) $incomeItems->sum('amount');

        $totalExpenses = (float) $this->expenseItems->sum('amount');
        $netIncome = $grossIncome === null ? null : $grossIncome - $totalExpenses;

        // Round 11 — Johan: "I keep capturing expenses and income and by
        // what do I divide once ready to get a monthly avg? we have to ask
        // the nr of months the bank statement is for." DISPLAY ONLY for
        // now — deliberately does NOT feed gross_income/meets_threshold
        // below until Johan confirms replacing the raw sum with this
        // average is the actual decision he wants (his own stated view:
        // yes, but he asked to confirm before it's wired in).
        $monthlyAverageGrossIncome = ($grossIncome !== null && $this->statement_months)
            ? round($grossIncome / $this->statement_months, 2)
            : null;
        $monthlyAverageExpenses = ($this->statement_months && $totalExpenses > 0)
            ? round($totalExpenses / $this->statement_months, 2)
            : null;

        $rentalApplication = $this->rentalApplication;
        $rent = $rentalApplication?->current_rental_amount !== null
            ? (float) $rentalApplication->current_rental_amount
            : null;

        $maxAffordableRent = $grossIncome !== null ? round($grossIncome * ($maxRentPercent / 100), 2) : null;

        $rentAsPercentOfGross = ($rent !== null && $grossIncome !== null && $grossIncome > 0)
            ? round(($rent / $grossIncome) * 100, 1)
            : null;

        $meetsThreshold = ($rent !== null && $maxAffordableRent !== null)
            ? $rent <= $maxAffordableRent
            : null;

        return [
            'gross_income' => $grossIncome,
            'net_income' => $netIncome,
            'statement_months' => $this->statement_months,
            'monthly_average_gross_income' => $monthlyAverageGrossIncome,
            'monthly_average_expenses' => $monthlyAverageExpenses,
            'rent' => $rent,
            'max_rent_percent' => $maxRentPercent,
            'max_affordable_rent' => $maxAffordableRent,
            'rent_as_percent_of_gross' => $rentAsPercentOfGross,
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
