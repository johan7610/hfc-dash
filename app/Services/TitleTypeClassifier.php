<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\PropertySettingItem;

/**
 * Single source of truth for "what title_type is this property?".
 *
 * Before this service existed the heuristic was duplicated twice
 * (MicSnapshotHydrator + PresentationReviewController) and the
 * subject classifier read only category → property_setting_items
 * mapping, which fails on agencies whose portfolio mixes title types
 * under one category (e.g. HFC's "Residential" covers 72 sectional
 * units + 24 vacant land subjects + 174 houses).
 *
 * Resolution order (per Phase A* keystone plan):
 *   1. property.property_type via the canonical heuristic
 *      ("Sectional Title"/"Apartment"/"Flat"/"Unit"/"Townhouse"/"Duplex"
 *       → sectional_title; "Vacant Land"/"Plot"/"Stand"/"Erf" → vacant_land;
 *       else full_title).
 *   2. property.category → property_setting_items.title_type (agency-scoped
 *      with null-agency system-defaults fallback). This is the safety net
 *      for rows where property_type is somehow blank.
 *   3. null — caller decides what to do.
 *
 * The TitleTypeClassifier::TITLE_* constants delegate to
 * PropertySettingItem so the existing constants stay canonical.
 */
final class TitleTypeClassifier
{
    public const TITLE_FULL      = PropertySettingItem::TITLE_FULL;
    public const TITLE_SECTIONAL = PropertySettingItem::TITLE_SECTIONAL;
    public const TITLE_VACANT    = PropertySettingItem::TITLE_VACANT;
    public const TITLE_OTHER     = PropertySettingItem::TITLE_OTHER;

    /**
     * Canonical heuristic — classify a free-text property_type string.
     *
     * Lifted verbatim from the duplicate bodies at
     * MicSnapshotHydrator::classifyCompTitleType (L312-325) and
     * PresentationReviewController::classifyCompTitleType (L584-597).
     * Both duplicates are deleted in this build.
     */
    public function fromPropertyType(?string $rawType): ?string
    {
        $t = strtolower((string) $rawType);
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'sectional') || str_contains($t, 'apartment') || str_contains($t, 'flat')
            || str_contains($t, 'unit') || str_contains($t, 'townhouse') || str_contains($t, 'duplex')) {
            return self::TITLE_SECTIONAL;
        }
        if (str_contains($t, 'vacant') || str_contains($t, 'plot') || str_contains($t, 'stand')
            || str_contains($t, 'erf') || $t === 'land') {
            // Bare "Land" — appeared in prospecting_listings as 2 rows that
            // were silently classified as full_title by the keyword default.
            // It's vacant land in every observed portal context. Equality
            // (not str_contains) so we don't swallow "Vacant Land" or any
            // other phrase that already matched above.
            return self::TITLE_VACANT;
        }
        return self::TITLE_FULL;
    }

    /**
     * Category fallback — read title_type from the agency's
     * property_setting_items category row. Falls back to system defaults
     * (null agency_id) when the agency hasn't customised its category
     * list yet. Lifted from MicSnapshotHydrator::resolveSubjectTitleType
     * L262-300.
     */
    public function fromCategory(int $agencyId, ?string $categoryName): ?string
    {
        if (!is_string($categoryName) || trim($categoryName) === '') {
            return null;
        }

        $row = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->where('group', PropertySettingItem::GROUP_CATEGORY)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($categoryName))])
            ->first(['title_type']);

        if (!$row) {
            $row = PropertySettingItem::withoutGlobalScopes()
                ->whereNull('agency_id')
                ->where('group', PropertySettingItem::GROUP_CATEGORY)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($categoryName))])
                ->first(['title_type']);
        }

        return $row && !empty($row->title_type) ? (string) $row->title_type : null;
    }

    /**
     * Build 7 — return the agency's configured display label for a
     * raw category string. The raw `properties.category` column tends
     * to be lowercase ("residential"); the agency's
     * property_setting_items row name is proper-case ("Residential").
     * Match case-insensitively + return the agency's casing. Falls
     * back to Str::title() on the raw input when no matching agency
     * row exists. Returns null when input itself is blank.
     */
    public function displayCategoryLabel(int $agencyId, ?string $rawCategoryName): ?string
    {
        if (!is_string($rawCategoryName) || trim($rawCategoryName) === '') {
            return null;
        }

        $row = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->where('group', PropertySettingItem::GROUP_CATEGORY)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($rawCategoryName))])
            ->first(['name']);

        if (!$row) {
            $row = PropertySettingItem::withoutGlobalScopes()
                ->whereNull('agency_id')
                ->where('group', PropertySettingItem::GROUP_CATEGORY)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($rawCategoryName))])
                ->first(['name']);
        }

        return $row && !empty($row->name)
            ? (string) $row->name
            : \Illuminate\Support\Str::title(trim($rawCategoryName));
    }

    /**
     * Resolve a property's title_type using property_type first, falling
     * back to category-driven mapping when property_type is blank.
     *
     * Used by PropertyObserver::saving() to populate properties.title_type
     * and by the presentation pipeline as a safety net when reading a
     * row that pre-dates the backfill (NULL column).
     */
    public function forProperty(Property $property): ?string
    {
        $fromType = $this->fromPropertyType($property->property_type);
        if ($fromType !== null) {
            return $fromType;
        }
        if ($property->agency_id) {
            return $this->fromCategory((int) $property->agency_id, $property->category);
        }
        return null;
    }
}
