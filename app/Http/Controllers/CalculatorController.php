<?php

namespace App\Http\Controllers;

use App\Models\PerformanceSetting;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function index()
    {
        $primeRate = (float) PerformanceSetting::get('sa_prime_rate', 11.75);

        return view('calculators.index', [
            'primeRate' => $primeRate,
        ]);
    }

    public function calculateCommission(Request $request)
    {
        $data = $request->validate([
            'sale_price' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $salePrice = (float) $data['sale_price'];
        $rate = (float) $data['commission_rate'];
        $vatRate = 0.15;

        $commissionExcVat = $salePrice * ($rate / 100);
        $vat = $commissionExcVat * $vatRate;
        $commissionIncVat = $commissionExcVat + $vat;

        return response()->json([
            'ok' => true,
            'commission_exc_vat' => round($commissionExcVat, 2),
            'vat' => round($vat, 2),
            'commission_inc_vat' => round($commissionIncVat, 2),
            'agent_split_50' => round($commissionExcVat * 0.50, 2),
            'agent_split_60' => round($commissionExcVat * 0.60, 2),
            'agent_split_70' => round($commissionExcVat * 0.70, 2),
        ]);
    }

    public function calculateBond(Request $request)
    {
        $data = $request->validate([
            'loan_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:50',
            'term_years' => 'required|integer|min:1|max:30',
        ]);

        $principal = (float) $data['loan_amount'];
        $annualRate = (float) $data['interest_rate'];
        $years = (int) $data['term_years'];

        $monthly = $this->calcMonthlyRepayment($principal, $annualRate, $years);
        $totalRepaid = $monthly * $years * 12;
        $totalInterest = $totalRepaid - $principal;

        // Comparison rows: prime+1%, prime+2%
        $monthlyPlus1 = $this->calcMonthlyRepayment($principal, $annualRate + 1, $years);
        $monthlyPlus2 = $this->calcMonthlyRepayment($principal, $annualRate + 2, $years);

        return response()->json([
            'ok' => true,
            'monthly_repayment' => round($monthly, 2),
            'total_repaid' => round($totalRepaid, 2),
            'total_interest' => round($totalInterest, 2),
            'monthly_plus_1' => round($monthlyPlus1, 2),
            'total_repaid_plus_1' => round($monthlyPlus1 * $years * 12, 2),
            'monthly_plus_2' => round($monthlyPlus2, 2),
            'total_repaid_plus_2' => round($monthlyPlus2 * $years * 12, 2),
        ]);
    }

    public function calculateTransferDuty(Request $request)
    {
        $data = $request->validate([
            'purchase_price' => 'required|numeric|min:0',
        ]);

        $price = (float) $data['purchase_price'];
        $duty = $this->calcTransferDuty($price);
        $effectiveRate = $price > 0 ? ($duty / $price) * 100 : 0;

        $bracket = $this->getTransferDutyBracket($price);

        return response()->json([
            'ok' => true,
            'transfer_duty' => round($duty, 2),
            'effective_rate' => round($effectiveRate, 2),
            'bracket' => $bracket,
        ]);
    }

    public function calculateTransferCosts(Request $request)
    {
        $data = $request->validate([
            'purchase_price' => 'required|numeric|min:0',
            'needs_bond' => 'required|boolean',
            'bond_amount' => 'nullable|numeric|min:0',
        ]);

        $price = (float) $data['purchase_price'];
        $needsBond = (bool) $data['needs_bond'];
        $bondAmount = $needsBond ? (float) ($data['bond_amount'] ?? $price) : 0;

        $transferDuty = $this->calcTransferDuty($price);
        $conveyancingFees = $this->estimateConveyancingFees($price);
        $deedsOfficePetties = $this->estimateDeedsOfficePetties($price);
        $subtotalTransfer = $transferDuty + $conveyancingFees + $deedsOfficePetties;

        $bondRegistration = 0;
        if ($needsBond && $bondAmount > 0) {
            $bondRegistration = $this->estimateBondRegistration($bondAmount);
        }

        $grandTotal = $subtotalTransfer + $bondRegistration;

        return response()->json([
            'ok' => true,
            'transfer_duty' => round($transferDuty, 2),
            'conveyancing_fees' => round($conveyancingFees, 2),
            'deeds_office_petties' => round($deedsOfficePetties, 2),
            'subtotal_transfer' => round($subtotalTransfer, 2),
            'bond_registration' => round($bondRegistration, 2),
            'grand_total' => round($grandTotal, 2),
        ]);
    }

    /**
     * Standard amortisation formula.
     * M = P * [r(1+r)^n] / [(1+r)^n - 1]
     */
    private function calcMonthlyRepayment(float $principal, float $annualRate, int $years): float
    {
        if ($principal <= 0 || $annualRate <= 0 || $years <= 0) {
            return 0;
        }

        $r = ($annualRate / 100) / 12;
        $n = $years * 12;
        $factor = pow(1 + $r, $n);

        return $principal * ($r * $factor) / ($factor - 1);
    }

    /**
     * SA Transfer Duty brackets (effective 1 March 2023).
     */
    private function calcTransferDuty(float $price): float
    {
        if ($price <= 1100000) {
            return 0;
        }
        if ($price <= 1512500) {
            return ($price - 1100000) * 0.03;
        }
        if ($price <= 2117500) {
            return 12375 + ($price - 1512500) * 0.06;
        }
        if ($price <= 2722500) {
            return 48675 + ($price - 2117500) * 0.08;
        }
        if ($price <= 12100000) {
            return 97075 + ($price - 2722500) * 0.11;
        }

        return 1128600 + ($price - 12100000) * 0.13;
    }

    private function getTransferDutyBracket(float $price): string
    {
        if ($price <= 1100000) {
            return 'R0 - R1,100,000: 0%';
        }
        if ($price <= 1512500) {
            return 'R1,100,001 - R1,512,500: 3% above R1,100,000';
        }
        if ($price <= 2117500) {
            return 'R1,512,501 - R2,117,500: R12,375 + 6% above R1,512,500';
        }
        if ($price <= 2722500) {
            return 'R2,117,501 - R2,722,500: R48,675 + 8% above R2,117,500';
        }
        if ($price <= 12100000) {
            return 'R2,722,501 - R12,100,000: R97,075 + 11% above R2,722,500';
        }

        return 'R12,100,001+: R1,128,600 + 13% above R12,100,000';
    }

    /**
     * Estimated conveyancing (transfer attorney) fees based on property value.
     * Guideline scale — approximate.
     */
    private function estimateConveyancingFees(float $price): float
    {
        if ($price <= 0) return 0;
        if ($price <= 500000) return 8500;
        if ($price <= 750000) return 11000;
        if ($price <= 1000000) return 14000;
        if ($price <= 1500000) return 17500;
        if ($price <= 2000000) return 22000;
        if ($price <= 3000000) return 28000;
        if ($price <= 5000000) return 38000;
        if ($price <= 10000000) return 55000;
        return 75000;
    }

    /**
     * Estimated deeds office, postage, and petties.
     */
    private function estimateDeedsOfficePetties(float $price): float
    {
        if ($price <= 0) return 0;
        if ($price <= 1000000) return 4500;
        if ($price <= 2000000) return 5500;
        if ($price <= 5000000) return 7000;
        return 9000;
    }

    /**
     * Estimated bond registration costs (attorney + deeds).
     */
    private function estimateBondRegistration(float $bondAmount): float
    {
        if ($bondAmount <= 0) return 0;
        if ($bondAmount <= 500000) return 12000;
        if ($bondAmount <= 750000) return 15000;
        if ($bondAmount <= 1000000) return 18000;
        if ($bondAmount <= 1500000) return 22000;
        if ($bondAmount <= 2000000) return 27000;
        if ($bondAmount <= 3000000) return 33000;
        if ($bondAmount <= 5000000) return 42000;
        return 55000;
    }
}
