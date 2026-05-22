<?php

namespace App\Services\Presentations;

use App\Models\Agency;
use App\Models\Property;
use App\Models\Prospecting\TrackedProperty;
use App\Support\MarketAnalytics\HaversineDistance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Presentations V2 — CMA coverage scorer.
 *
 * Phase 2: counted registered deals in the property's suburb.
 * Phase 3b: now counts the UNION of (deals + market_report_comp_rows + presentation_sold_comps),
 *   honouring the property's effective comp scope (radius vs suburb-only) +
 *   distinct-by-fingerprint (scheme + section if sectional, else address + sale_date)
 *   so the same sale present in multiple sources doesn't double-count.
 *
 * Pure read, deterministic, no DB writes. countComps is protected so tests
 * can stub it without hitting the DB.
 */
class CmaCoverageService
{
    public const STATE_RICH = 'rich';
    public const STATE_MODERATE = 'moderate';
    public const STATE_THIN = 'thin';
    public const STATE_NONE = 'none';

    public const DEFAULT_THRESHOLD_RICH     = 6;
    public const DEFAULT_THRESHOLD_MODERATE = 3;
    public const DEFAULT_THRESHOLD_THIN     = 1;
    public const DEFAULT_PERIOD_MONTHS      = 12;

    public const SCOPE_RADIUS_ALL  = 'radius_all';
    public const SCOPE_SUBURB_ONLY = 'suburb_only';

    public function scoreForProperty(Property $property, ?int $periodMonths = null): array
    {
        $lat = $property->latitude !== null && $property->latitude !== '' ? (float) $property->latitude : null;
        $lng = $property->longitude !== null && $property->longitude !== '' ? (float) $property->longitude : null;

        return $this->score(
            agencyId:     (int) $property->agency_id,
            suburb:       (string) ($property->suburb ?? ''),
            propertyType: (string) ($property->property_type ?? ''),
            subjectLat:   $lat,
            subjectLng:   $lng,
            periodMonths: $periodMonths,
        );
    }

    public function scoreForTrackedProperty(TrackedProperty $property, ?int $periodMonths = null): array
    {
        $lat = $property->latitude !== null && $property->latitude !== '' ? (float) $property->latitude : null;
        $lng = $property->longitude !== null && $property->longitude !== '' ? (float) $property->longitude : null;

        return $this->score(
            agencyId:     (int) $property->agency_id,
            suburb:       (string) ($property->suburb ?? ''),
            propertyType: (string) ($property->property_type ?? ''),
            subjectLat:   $lat,
            subjectLng:   $lng,
            periodMonths: $periodMonths,
        );
    }

    private function score(
        int $agencyId,
        string $suburb,
        string $propertyType,
        ?float $subjectLat,
        ?float $subjectLng,
        ?int $periodMonths,
    ): array {
        $thresholds = $this->thresholdsForAgency($agencyId);
        $window     = $periodMonths ?? $thresholds['period_months'];

        $compCount = $this->countComps($suburb, $window, $thresholds['scope'], $thresholds['radius_m'], $subjectLat, $subjectLng);

        $state = match (true) {
            $compCount === 0                       => self::STATE_NONE,
            $compCount >= $thresholds['rich']      => self::STATE_RICH,
            $compCount >= $thresholds['moderate']  => self::STATE_MODERATE,
            $compCount >= $thresholds['thin']      => self::STATE_THIN,
            default                                => self::STATE_NONE,
        };

        return [
            'state'           => $state,
            'comp_count'      => $compCount,
            'period_months'   => $window,
            'suburb'          => $suburb,
            'property_type'   => $propertyType,
            'comp_scope'      => $thresholds['scope'],
            'comp_radius_m'   => $thresholds['radius_m'],
            'subject_geo'     => $subjectLat !== null && $subjectLng !== null,
            'thresholds'      => [
                'rich'     => $thresholds['rich'],
                'moderate' => $thresholds['moderate'],
                'thin'     => $thresholds['thin'],
            ],
            'can_generate'    => $state !== self::STATE_NONE,
            'recommendation'  => $this->recommendation($state, $compCount, $thresholds),
        ];
    }

    /**
     * Count the union of comparable sales the engine would see for this
     * property. Three sources:
     *   1. `deals` (HFC's own registered transactions)
     *   2. `market_report_comp_rows` (MIC shared pool — CMA Info imports)
     *   3. `presentation_sold_comps` (legacy per-presentation manual uploads)
     *
     * Deduped by fingerprint:
     *   sectional → strtoupper(scheme_name)|section_number|sale_date|sale_price
     *   full      → strtolower(address)|sale_date|sale_price
     *
     * Protected so tests / coverage tools can stub.
     */
    protected function countComps(
        string $suburb,
        int $periodMonths,
        string $scope,
        int $radiusM,
        ?float $subjectLat,
        ?float $subjectLng,
    ): int {
        if ($suburb === '') {
            return 0;
        }

        $dateFrom = Carbon::today()->subMonths($periodMonths)->toDateString();
        $dateTo   = Carbon::today()->toDateString();
        $like     = '%' . mb_strtolower(trim($suburb)) . '%';

        $fingerprints = [];

        // 1. Deals — suburb LIKE on property_address (geo not available).
        $dealRows = DB::table('deals')
            ->whereNotNull('registration_date')
            ->where(function ($q) {
                $q->whereNull('accepted_status')->orWhere('accepted_status', '!=', 'D');
            })
            ->whereBetween('registration_date', [$dateFrom, $dateTo])
            ->whereRaw('LOWER(property_address) LIKE ?', [$like])
            ->select(['property_address', 'registration_date', 'property_value'])
            ->get();
        foreach ($dealRows as $r) {
            $fingerprints[$this->fingerprintDeal($r)] = true;
        }

        // 2. MIC market_report_comp_rows — scope-branched read.
        $micQuery = DB::table('market_report_comp_rows')
            ->whereNull('deleted_at')
            ->where('row_type', 'comp')
            ->whereNotNull('sale_date')
            ->whereNotNull('sale_price')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->select(['scheme_name', 'section_number', 'address', 'sale_date', 'sale_price', 'suburb_normalised', 'latitude', 'longitude']);

        $micRows = $micQuery->get();
        foreach ($micRows as $r) {
            if (!$this->compInScope($r, $scope, $like, $radiusM, $subjectLat, $subjectLng)) continue;
            $fingerprints[$this->fingerprintMic($r)] = true;
        }

        // 3. Legacy presentation_sold_comps fallback (suburb LIKE only).
        $psRows = DB::table('presentation_sold_comps')
            ->whereNull('deleted_at')
            ->whereNotNull('sold_date')
            ->whereNotNull('sold_price_inc')
            ->whereBetween('sold_date', [$dateFrom, $dateTo])
            ->where(function ($q) use ($like) {
                $q->whereNull('suburb')->orWhereRaw('LOWER(suburb) LIKE ?', [$like]);
            })
            ->select(['suburb', 'sold_date', 'sold_price_inc'])
            ->get();
        foreach ($psRows as $r) {
            $fingerprints[$this->fingerprintPs($r)] = true;
        }

        return count($fingerprints);
    }

    /**
     * Does a market_report_comp_rows row satisfy the configured scope?
     */
    private function compInScope(object $row, string $scope, string $suburbLike, int $radiusM, ?float $subjectLat, ?float $subjectLng): bool
    {
        if ($scope === self::SCOPE_SUBURB_ONLY) {
            return $this->matchesSuburb($row->suburb_normalised, $suburbLike);
        }
        // radius_all — Haversine when both sides have geo, else fall back to suburb.
        if ($subjectLat !== null && $subjectLng !== null && $row->latitude !== null && $row->longitude !== null) {
            $d = HaversineDistance::distanceMetres($subjectLat, $subjectLng, (float) $row->latitude, (float) $row->longitude);
            return $d <= max(1, $radiusM);
        }
        return $this->matchesSuburb($row->suburb_normalised, $suburbLike);
    }

    private function matchesSuburb(?string $rowSuburb, string $needleLike): bool
    {
        if (!is_string($rowSuburb) || $rowSuburb === '') return false;
        $needle = trim($needleLike, '%');
        return $needle === '' || str_contains(mb_strtolower($rowSuburb), $needle);
    }

    private function fingerprintDeal(object $r): string
    {
        return 'D|' . mb_strtolower(trim((string) ($r->property_address ?? ''))) . '|' . (string) $r->registration_date . '|' . (int) ($r->property_value ?? 0);
    }

    private function fingerprintMic(object $r): string
    {
        if (!empty($r->scheme_name) && !empty($r->section_number)) {
            return 'M|' . strtoupper((string) $r->scheme_name) . '|S' . (string) $r->section_number . '|' . (string) $r->sale_date . '|' . (int) ($r->sale_price ?? 0);
        }
        $addr = $r->address ?? '';
        return 'M|' . mb_strtolower(trim((string) $addr)) . '|' . (string) $r->sale_date . '|' . (int) ($r->sale_price ?? 0);
    }

    private function fingerprintPs(object $r): string
    {
        return 'P|' . mb_strtolower((string) ($r->suburb ?? '')) . '|' . (string) $r->sold_date . '|' . (int) ($r->sold_price_inc ?? 0);
    }

    /**
     * @return array{rich:int,moderate:int,thin:int,period_months:int,scope:string,radius_m:int}
     */
    private function thresholdsForAgency(int $agencyId): array
    {
        $agency = $agencyId > 0 ? Agency::find($agencyId) : null;

        return [
            'rich'          => (int) ($agency->presentations_coverage_rich_threshold ?? self::DEFAULT_THRESHOLD_RICH),
            'moderate'      => (int) ($agency->presentations_coverage_moderate_threshold ?? self::DEFAULT_THRESHOLD_MODERATE),
            'thin'          => (int) ($agency->presentations_coverage_thin_threshold ?? self::DEFAULT_THRESHOLD_THIN),
            'period_months' => (int) ($agency->presentations_default_period_months ?? self::DEFAULT_PERIOD_MONTHS),
            'scope'         => (string) ($agency->presentations_default_comp_scope ?? self::SCOPE_RADIUS_ALL),
            'radius_m'      => (int) ($agency->presentations_default_radius_m ?? 1000),
        ];
    }

    private function recommendation(string $state, int $compCount, array $thresholds): string
    {
        $scopeLabel = $thresholds['scope'] === self::SCOPE_RADIUS_ALL
            ? sprintf('within %dm', $thresholds['radius_m'])
            : 'suburb-only';

        return match ($state) {
            self::STATE_RICH     => sprintf('Strong data — %d recent comparable sales (%s).', $compCount, $scopeLabel),
            self::STATE_MODERATE => sprintf('Moderate data — %d recent comparable sales (%s). Stronger comps available with more uploads.', $compCount, $scopeLabel),
            self::STATE_THIN     => sprintf('Thin data — %d recent comparable sale%s (%s). Upload more CMAs to strengthen.', $compCount, $compCount === 1 ? '' : 's', $scopeLabel),
            default              => sprintf('No comparable sales found (%s). Upload CMAs first?', $scopeLabel),
        };
    }
}
