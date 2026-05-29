<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use App\Models\Presentation;
use App\Models\PresentationVersion;
use App\Models\PropertySettingItem;
use Illuminate\Support\Facades\Log;

/**
 * Build 3 — Centralise the "which condition applies to this version?"
 * decision so the answer is identical for the review screen, the live
 * recalc endpoint, and the PDF compile path.
 *
 * Priority (per Build 3 prompt §B (4)):
 *   1. presentation_versions.condition_level_id   (agent's override on review)
 *   2. properties.condition_level_id              (property's recorded condition)
 *   3. null                                       (no adjustment — baseline only)
 *
 * Snapshot rule:
 *   When a version is published, the agent's choice + the agency's
 *   adjustment_pct at that moment are frozen on the version row
 *   (condition_adjustment_pct + condition_label). Subsequent agency
 *   settings drift never moves the historic PDF — that's the whole
 *   reason we snapshot. Live recompute reads agency settings; published
 *   PDF reads the version snapshot.
 *
 * Adjustment shape:
 *   Lower and Upper stay as bookend extremes (CMA Info convention —
 *   condition doesn't compress the range). Only Middle moves:
 *
 *       middle_adjusted = round(middle * (1 + pct / 100))
 *
 *   Negative pct shrinks, positive grows. Range gate at the validator
 *   (-100 exclusive < x <= 200).
 */
final class ConditionAdjustmentService
{
    /**
     * Resolve the condition level for live computation (review screen,
     * recalc endpoint). Reads agency settings, NOT the version snapshot —
     * the version snapshot is only the source of truth AFTER publish.
     *
     * @return array{level: ?PropertySettingItem, source: string}
     */
    public function resolveLive(PresentationVersion $version, ?Presentation $presentation = null): array
    {
        $presentation ??= $version->presentation;

        // 1. Version override (agent picked on review).
        // withoutGlobalScopes() bypasses BelongsToAgency (this resolver
        // is the trust boundary — we explicitly check agency_id below)
        // but we still skip soft-deleted rows so an agency-deleted
        // condition gracefully falls through to the next source.
        if ($version->condition_level_id) {
            $level = PropertySettingItem::withoutGlobalScopes()
                ->where('id', $version->condition_level_id)
                ->where('agency_id', $version->agency_id)
                ->whereNull('deleted_at')
                ->first();
            if ($level) return ['level' => $level, 'source' => 'version_override'];
        }

        // 2. Property's recorded condition.
        $propertyConditionId = $presentation?->property?->condition_level_id;
        if ($propertyConditionId) {
            $level = PropertySettingItem::withoutGlobalScopes()
                ->where('id', $propertyConditionId)
                ->where('agency_id', $version->agency_id)
                ->whereNull('deleted_at')
                ->first();
            if ($level) return ['level' => $level, 'source' => 'property_default'];
        }

        // 3. Nothing — baseline only.
        Log::info('[PRES-INFO] no condition resolved — baseline valuation applied', [
            'presentation_version_id' => $version->id,
            'presentation_id'         => $presentation?->id,
        ]);
        return ['level' => null, 'source' => 'none'];
    }

    /**
     * Apply a condition pct to a Middle band. Lower/Upper untouched.
     *
     * @param  int|null  $middle      The Middle band value to adjust.
     * @param  float|null $pct        Adjustment % (e.g. 12.0 for +12%, -15.0 for -15%).
     * @return array{
     *   adjusted: ?int,
     *   baseline: ?int,
     *   pct: ?float,
     *   was_applied: bool,
     * }
     */
    public function applyToMiddle(?int $middle, ?float $pct): array
    {
        if ($middle === null) {
            return ['adjusted' => null, 'baseline' => null, 'pct' => $pct, 'was_applied' => false];
        }
        if ($pct === null || abs($pct) < 0.005) {
            return ['adjusted' => $middle, 'baseline' => $middle, 'pct' => $pct ?? 0.0, 'was_applied' => false];
        }
        $adjusted = (int) round($middle * (1 + ($pct / 100)));
        return ['adjusted' => $adjusted, 'baseline' => $middle, 'pct' => $pct, 'was_applied' => true];
    }

    /**
     * Snapshot the resolved condition onto the version row. Called at
     * publish time so the version is frozen against future agency
     * settings drift.
     *
     * Idempotent: snapshotting an already-snapshotted version with the
     * same source = no-op.
     */
    public function snapshotOnVersion(PresentationVersion $version, ?PropertySettingItem $level): void
    {
        if ($level === null) {
            $version->forceFill([
                'condition_level_id'        => null,
                'condition_adjustment_pct'  => null,
                'condition_label'           => null,
            ])->save();
            return;
        }

        $version->forceFill([
            'condition_level_id'        => $level->id,
            'condition_adjustment_pct'  => (float) $level->adjustment_pct,
            'condition_label'           => $level->name,
        ])->save();
    }
}
