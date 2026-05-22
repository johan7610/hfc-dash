<?php

namespace App\Services\Presentations;

use App\Models\Agency;
use App\Models\Property;
use App\Models\Prospecting\TrackedProperty;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Presentations V2 Phase 2 — CMA coverage scorer.
 *
 * Counts agency-wide sold transactions (registered deals) for a property's
 * suburb + period window and maps the count to a coverage state via the
 * agency's configured thresholds. Used by the one-button generator UI to
 * decide whether to render a "ready" or "thin data" hint.
 *
 * Pure read, deterministic, no DB writes.
 *
 * Spec: .ai/specs/presentations.md §6 Phase 2
 */
class CmaCoverageService
{
    public const STATE_RICH = 'rich';
    public const STATE_MODERATE = 'moderate';
    public const STATE_THIN = 'thin';
    public const STATE_NONE = 'none';

    public const DEFAULT_THRESHOLD_RICH = 6;
    public const DEFAULT_THRESHOLD_MODERATE = 3;
    public const DEFAULT_THRESHOLD_THIN = 1;
    public const DEFAULT_PERIOD_MONTHS = 12;

    /**
     * @return array{
     *   state: string,
     *   comp_count: int,
     *   period_months: int,
     *   suburb: string,
     *   property_type: string,
     *   thresholds: array{rich:int,moderate:int,thin:int},
     *   can_generate: bool,
     *   recommendation: string,
     * }
     */
    public function scoreForProperty(Property $property, ?int $periodMonths = null): array
    {
        return $this->score(
            agencyId:      (int) $property->agency_id,
            suburb:        (string) ($property->suburb ?? ''),
            propertyType:  (string) ($property->property_type ?? ''),
            periodMonths:  $periodMonths,
        );
    }

    /**
     * @return array{
     *   state: string,
     *   comp_count: int,
     *   period_months: int,
     *   suburb: string,
     *   property_type: string,
     *   thresholds: array{rich:int,moderate:int,thin:int},
     *   can_generate: bool,
     *   recommendation: string,
     * }
     */
    public function scoreForTrackedProperty(TrackedProperty $property, ?int $periodMonths = null): array
    {
        return $this->score(
            agencyId:      (int) $property->agency_id,
            suburb:        (string) ($property->suburb ?? ''),
            propertyType:  (string) ($property->property_type ?? ''),
            periodMonths:  $periodMonths,
        );
    }

    private function score(int $agencyId, string $suburb, string $propertyType, ?int $periodMonths): array
    {
        $thresholds = $this->thresholdsForAgency($agencyId);
        $window     = $periodMonths ?? $thresholds['period_months'];

        $compCount = $this->countComps($suburb, $window);

        $state = match (true) {
            $compCount === 0                              => self::STATE_NONE,
            $compCount >= $thresholds['rich']             => self::STATE_RICH,
            $compCount >= $thresholds['moderate']         => self::STATE_MODERATE,
            $compCount >= $thresholds['thin']             => self::STATE_THIN,
            default                                       => self::STATE_NONE,
        };

        return [
            'state'           => $state,
            'comp_count'      => $compCount,
            'period_months'   => $window,
            'suburb'          => $suburb,
            'property_type'   => $propertyType,
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
     * Count registered deals in the suburb within the last $periodMonths.
     * Mirrors InternalDealsAdapter's filter (LIKE suburb on property_address,
     * excludes declined deals) — agency-wide and free of presentation_id
     * scoping so coverage reflects what the engine WOULD see for fresh runs.
     *
     * Protected so tests / coverage tools can stub the DB lookup without
     * having to hit the deals table.
     */
    protected function countComps(string $suburb, int $periodMonths): int
    {
        if ($suburb === '') {
            return 0;
        }

        $like = '%' . mb_strtolower(trim($suburb)) . '%';
        $dateFrom = Carbon::today()->subMonths($periodMonths)->toDateString();
        $dateTo   = Carbon::today()->toDateString();

        return (int) DB::table('deals')
            ->whereNotNull('registration_date')
            ->where(function ($q) {
                $q->whereNull('accepted_status')
                  ->orWhere('accepted_status', '!=', 'D');
            })
            ->whereBetween('registration_date', [$dateFrom, $dateTo])
            ->whereRaw('LOWER(property_address) LIKE ?', [$like])
            ->count();
    }

    /**
     * Read thresholds + window from the agency record; fall back to defaults.
     *
     * @return array{rich:int,moderate:int,thin:int,period_months:int}
     */
    private function thresholdsForAgency(int $agencyId): array
    {
        $agency = $agencyId > 0 ? Agency::find($agencyId) : null;

        return [
            'rich'          => (int) ($agency->presentations_coverage_rich_threshold ?? self::DEFAULT_THRESHOLD_RICH),
            'moderate'      => (int) ($agency->presentations_coverage_moderate_threshold ?? self::DEFAULT_THRESHOLD_MODERATE),
            'thin'          => (int) ($agency->presentations_coverage_thin_threshold ?? self::DEFAULT_THRESHOLD_THIN),
            'period_months' => (int) ($agency->presentations_default_period_months ?? self::DEFAULT_PERIOD_MONTHS),
        ];
    }

    private function recommendation(string $state, int $compCount, array $thresholds): string
    {
        return match ($state) {
            self::STATE_RICH     => sprintf('Strong data — %d recent comparable sales.', $compCount),
            self::STATE_MODERATE => sprintf('Moderate data — %d recent comparable sales. Stronger comps available with more uploads.', $compCount),
            self::STATE_THIN     => sprintf('Thin data — %d recent comparable sale%s. Upload more CMAs to strengthen.', $compCount, $compCount === 1 ? '' : 's'),
            default              => 'No comparable sales found. Generate may be limited. Upload CMAs first?',
        };
    }
}
