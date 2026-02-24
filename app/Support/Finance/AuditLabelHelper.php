<?php

namespace App\Support\Finance;

use Carbon\Carbon;

class AuditLabelHelper
{
    /**
     * Human-readable labels for known definition keys.
     */
    private const LABELS = [
        'deal.total_commission_ex_vat'                                  => 'Commission (ex VAT)',
        'deal.total_commission_inc_vat'                                  => 'Commission (inc VAT)',
        'deal.company_income_ex_vat.side_listing'                        => 'Company Income — Listing Side',
        'deal.company_income_ex_vat.side_selling'                        => 'Company Income — Selling Side',
        'deal.company_retained_ex_vat'                                   => 'Company Retained',
        'deal.agent_income_ex_vat.by_agent'                              => 'Agent Income Breakdown',
        'agent_period.money.total_nondeclined.agent_income_ex_vat'       => 'Agent Income (Period Total)',
        'agent_period.money.total_nondeclined.retained_ex_vat'           => 'Company Retained from Agent (Period Total)',
        'agent_period.money.registered_granted.agent_income_ex_vat'      => 'Agent Income (Registered + Granted)',
        'agent_period.money.registered_granted.retained_ex_vat'          => 'Company Retained from Agent (Reg + Granted)',
        'agent_period.value.total_nondeclined.property_value_inc_vat'    => 'Property Value (Period Total, inc VAT)',
        'agent_period.value.registered_granted.property_value_inc_vat'   => 'Property Value (Reg + Granted, inc VAT)',
        'branch_period.money.total_nondeclined.team_agent_income_ex_vat' => 'Branch Team Income (Period Total)',
        'branch_period.money.registered_granted.team_agent_income_ex_vat'=> 'Branch Team Income (Reg + Granted)',
        'branch_period.value.total_nondeclined.property_value_inc_vat'   => 'Branch Property Value (Period Total)',
        'branch_period.value.registered_granted.property_value_inc_vat'  => 'Branch Property Value (Reg + Granted)',
        'company_period.money.total_nondeclined.team_agent_income_ex_vat'=> 'Company Team Income (Period Total)',
        'company_period.money.registered_granted.team_agent_income_ex_vat'=> 'Company Team Income (Reg + Granted)',
        'company_period.value.total_nondeclined.property_value_inc_vat'  => 'Company Property Value (Period Total)',
        'company_period.value.registered_granted.property_value_inc_vat' => 'Company Property Value (Reg + Granted)',
    ];

    /**
     * Per-stage dynamic label patterns.
     */
    private const STAGE_PATTERNS = [
        'agent_period.deals.{stage}.count'                     => '{Stage} Deals (Count)',
        'agent_period.money.{stage}.agent_income_ex_vat'       => 'Agent Income — {Stage}',
        'agent_period.money.{stage}.breakdown_json'            => 'Agent Breakdown — {Stage}',
        'branch_period.deals.{stage}.count'                    => 'Branch {Stage} Deals (Count)',
        'branch_period.money.{stage}.team_agent_income_ex_vat' => 'Branch Team Income — {Stage}',
        'branch_period.money.{stage}.breakdown_json'           => 'Branch Breakdown — {Stage}',
        'company_period.deals.{stage}.count'                   => 'Company {Stage} Deals (Count)',
        'company_period.money.{stage}.team_agent_income_ex_vat'=> 'Company Team Income — {Stage}',
        'company_period.money.{stage}.breakdown_json'          => 'Company Breakdown — {Stage}',
    ];

    /**
     * Get human-readable label for a definition key.
     */
    public static function label(string $key): string
    {
        if (isset(self::LABELS[$key])) {
            return self::LABELS[$key];
        }

        foreach (['pending', 'granted', 'registered', 'declined'] as $stage) {
            foreach (self::STAGE_PATTERNS as $pattern => $labelPattern) {
                $concretePattern = str_replace('{stage}', $stage, $pattern);
                if ($key === $concretePattern) {
                    return str_replace('{Stage}', ucfirst($stage), $labelPattern);
                }
            }
        }

        return ucwords(str_replace(['.', '_'], ' ', $key));
    }

    /**
     * Format a numeric value as ZAR currency: "R 1,234.56"
     */
    public static function zar(?float $value): string
    {
        if ($value === null) {
            return '—';
        }
        return 'R ' . number_format($value, 2, '.', ',');
    }

    /**
     * Format a period string (YYYY-MM) to readable: "February 2026"
     */
    public static function periodLabel(string $period): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $period)->format('F Y');
        } catch (\Throwable $e) {
            return $period;
        }
    }

    /**
     * Summarise a JSON value for display.
     */
    public static function jsonSummary($json): string
    {
        if ($json === null) {
            return '—';
        }

        if (is_string($json)) {
            $json = json_decode($json, true);
            if ($json === null) {
                return '—';
            }
        }

        if (!is_array($json)) {
            return '—';
        }

        // by_agent format: { "agent_id": amount, ... }
        if (isset($json['by_agent']) && is_array($json['by_agent'])) {
            $agents = $json['by_agent'];
            $count = count($agents);
            $total = array_sum($agents);
            return $count . ' agent' . ($count !== 1 ? 's' : '') . ' totalling ' . self::zar($total);
        }

        // Breakdown format: [ {deal_id: X, amount_ex_vat: Y}, ... ]
        if (isset($json[0]['deal_id'])) {
            $count = count($json);
            $total = array_sum(array_column($json, 'amount_ex_vat'));
            return $count . ' deal' . ($count !== 1 ? 's' : '') . ' totalling ' . self::zar($total);
        }

        // Flat numeric map
        if (!empty($json)) {
            $allNumeric = true;
            foreach ($json as $v) {
                if (!is_numeric($v)) {
                    $allNumeric = false;
                    break;
                }
            }
            if ($allNumeric) {
                $count = count($json);
                $total = array_sum($json);
                return $count . ' entries totalling ' . self::zar($total);
            }
        }

        return '[json]';
    }
}
