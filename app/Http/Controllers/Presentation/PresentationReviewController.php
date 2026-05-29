<?php

declare(strict_types=1);

namespace App\Http\Controllers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\AgentOverride;
use App\Models\PresentationSoldComp;
use App\Models\PresentationVersion;
use App\Models\PropertySettingItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Build 2 — agent's pre-flight review screen.
 *
 * Routes:
 *   GET  /corex/presentations/{version}/review                 → show
 *   POST /corex/presentations/{version}/review/comps/{comp}    → toggleComp
 *   POST /corex/presentations/{version}/publish                → publish
 *   POST /corex/presentations/{version}/revert                 → revert
 *
 * Status flow:
 *   compile → awaiting_review → published
 *                             → archived (revert)
 *
 * Concurrent reviewer guard:
 *   reviewer_user_id + reviewer_locked_at; window of REVIEWER_LOCK_MINUTES.
 *   A second agent gets a banner; if they confirm takeover, the original
 *   lock is overwritten and a 'review_takeover' override is logged.
 */
final class PresentationReviewController extends Controller
{
    /** Render the review screen. */
    public function show(Request $request, PresentationVersion $version)
    {
        $this->authoriseReviewer($request, $version);

        // Build 2 robustness — re-validate the comp set against
        // soft-deleted rows in case anything was archived between
        // compile and review. Excluded comps are auto-logged with
        // override_type='comp_unavailable' so the audit captures the
        // implicit drop. The "should-be included" list is recomputed
        // from the version snapshot every render so it self-heals.
        $unavailableLogged = $this->reconcileSoftDeletedComps($request, $version);

        // Concurrent-reviewer detection. Lock is per-agent; a second
        // agent sees the banner and can take over (separate POST).
        $currentReviewer = $version->reviewerUser;
        $isLockedByOther = $version->reviewer_user_id
            && $version->reviewer_user_id !== $request->user()->id
            && $version->isReviewerLockActive();

        // Take the lock for this agent if no other live lock exists.
        if (!$isLockedByOther) {
            $version->forceFill([
                'reviewer_user_id'    => $request->user()->id,
                'reviewer_locked_at'  => now(),
            ])->save();
        }

        // Hydrate the data the Blade renders. Compiler already stored a
        // data_snapshot_json on the version; for the review screen we
        // need the LIVE comp list (with the included-set applied) and
        // a slim subject dict.
        $presentation = $version->presentation()->with('property')->first();
        $allComps     = PresentationSoldComp::query()
            ->where('presentation_id', $version->presentation_id)
            ->whereNull('deleted_at')
            ->orderByDesc('sold_date')
            ->get();

        $includedIds = $version->included_comp_ids_json
            ?: $allComps->pluck('id')->all();

        // title_type resolution for subject + cross-type warning flags
        // per comp. Mirrors MicSnapshotHydrator's classifier.
        $subjectTitleType = $this->resolveSubjectTitleType($version, $presentation);

        $compRows = $allComps->map(function ($c) use ($includedIds, $subjectTitleType) {
            $raw = is_string($c->raw_row_json)
                ? (json_decode($c->raw_row_json, true) ?: [])
                : ((array) $c->raw_row_json ?: []);
            $compTitleType = $this->classifyCompTitleType($c->property_type);
            return [
                'id'              => $c->id,
                'address'         => $raw['address'] ?? '—',
                'sale_date'       => optional($c->sold_date)->format('Y-m-d'),
                'sold_price_inc'  => $c->sold_price_inc,
                'property_type'   => $c->property_type,
                'size_m2'         => $c->size_m2,
                'r_per_m2'        => ($c->size_m2 && $c->sold_price_inc)
                    ? (int) round($c->sold_price_inc / $c->size_m2) : null,
                'lat'             => $raw['latitude'] ?? null,
                'lng'             => $raw['longitude'] ?? null,
                'title_type'      => $compTitleType,
                'is_included'     => in_array($c->id, $includedIds, true),
                'is_cross_type'   => $subjectTitleType !== null && $subjectTitleType !== $compTitleType,
            ];
        })->all();

        return view('presentations.review', [
            'version'           => $version,
            'presentation'      => $presentation,
            'compRows'          => $compRows,
            'subjectTitleType'  => $subjectTitleType,
            'isLockedByOther'   => $isLockedByOther,
            'currentReviewer'   => $currentReviewer,
            'unavailableLogged' => $unavailableLogged,
        ]);
    }

    /**
     * Toggle a comp's included flag. Writes a row to agent_overrides
     * and returns the updated row state so the JS can render
     * optimistically. Idempotent — re-POSTing the same intent is a
     * no-op log entry skipped silently.
     */
    public function toggleComp(Request $request, PresentationVersion $version, PresentationSoldComp $comp): JsonResponse
    {
        $this->authoriseReviewer($request, $version);

        $request->validate([
            'included' => 'required|boolean',
        ]);

        if ((int) $comp->presentation_id !== (int) $version->presentation_id) {
            return response()->json(['error' => 'comp_not_in_version'], 422);
        }

        $current = $version->included_comp_ids_json ?? PresentationSoldComp::query()
            ->where('presentation_id', $version->presentation_id)
            ->whereNull('deleted_at')
            ->pluck('id')->all();
        $current = array_values(array_unique(array_map('intval', $current)));

        $wantIncluded = (bool) $request->boolean('included');
        $wasIncluded  = in_array((int) $comp->id, $current, true);

        if ($wantIncluded === $wasIncluded) {
            // No-op — idempotent re-toggle (e.g. double-click). Return
            // current state without logging.
            return response()->json([
                'ok'           => true,
                'comp_id'      => $comp->id,
                'is_included'  => $wasIncluded,
                'no_op'        => true,
            ]);
        }

        if ($wantIncluded) {
            $current[] = (int) $comp->id;
        } else {
            $current = array_values(array_diff($current, [(int) $comp->id]));
        }

        DB::transaction(function () use ($version, $current, $comp, $request, $wantIncluded, $wasIncluded) {
            $version->forceFill(['included_comp_ids_json' => $current])->save();

            AgentOverride::create([
                'agency_id'               => $version->agency_id,
                'presentation_version_id' => $version->id,
                'user_id'                 => $request->user()->id,
                'override_type'           => $wantIncluded
                    ? AgentOverride::TYPE_COMP_INCLUDED
                    : AgentOverride::TYPE_COMP_EXCLUDED,
                'target_id'               => (string) $comp->id,
                'before_value'            => ['is_included' => $wasIncluded],
                'after_value'             => ['is_included' => $wantIncluded],
            ]);
        });

        return response()->json([
            'ok'           => true,
            'comp_id'      => $comp->id,
            'is_included'  => $wantIncluded,
            'override_id'  => AgentOverride::where('presentation_version_id', $version->id)
                                  ->where('target_id', (string) $comp->id)
                                  ->latest('id')->value('id'),
        ]);
    }

    /**
     * Publish the version. Idempotent — re-publish is a no-op.
     * Returns JSON with the public/show URL for the JS to navigate
     * the (already-open) review tab to.
     */
    public function publish(Request $request, PresentationVersion $version): JsonResponse
    {
        $this->authoriseReviewer($request, $version);

        if ($version->review_status === PresentationVersion::REVIEW_PUBLISHED) {
            return response()->json([
                'ok'         => true,
                'already'    => true,
                'public_url' => route('presentations.show', $version->presentation_id),
            ]);
        }

        $version->forceFill([
            'review_status' => PresentationVersion::REVIEW_PUBLISHED,
            'published_at'  => now(),
        ])->save();

        return response()->json([
            'ok'         => true,
            'public_url' => route('presentations.show', $version->presentation_id),
        ]);
    }

    /**
     * Revert the version — soft-delete it and bounce the agent back
     * to the source property. Logged with override_type=field_edited
     * (target_id='review_status').
     */
    public function revert(Request $request, PresentationVersion $version): JsonResponse
    {
        $this->authoriseReviewer($request, $version);

        DB::transaction(function () use ($version, $request) {
            AgentOverride::create([
                'agency_id'               => $version->agency_id,
                'presentation_version_id' => $version->id,
                'user_id'                 => $request->user()->id,
                'override_type'           => AgentOverride::TYPE_FIELD_EDITED,
                'target_id'               => 'review_status',
                'before_value'            => ['review_status' => $version->review_status],
                'after_value'             => ['review_status' => PresentationVersion::REVIEW_ARCHIVED],
            ]);

            $version->forceFill([
                'review_status' => PresentationVersion::REVIEW_ARCHIVED,
                'archived_at'   => now(),
            ])->save();
            $version->delete();
        });

        // Bounce the agent's review tab back to the source property —
        // the property tab they came from stays open separately.
        $propertyId = $version->presentation->property_id ?? null;
        return response()->json([
            'ok'           => true,
            'property_url' => $propertyId
                ? route('corex.properties.show', $propertyId)
                : route('presentations.index'),
        ]);
    }

    /**
     * Take over the review lock from another agent.
     */
    public function takeover(Request $request, PresentationVersion $version): JsonResponse
    {
        $this->authoriseReviewer($request, $version);

        $previousUserId = $version->reviewer_user_id;
        DB::transaction(function () use ($version, $request, $previousUserId) {
            $version->forceFill([
                'reviewer_user_id'   => $request->user()->id,
                'reviewer_locked_at' => now(),
            ])->save();

            AgentOverride::create([
                'agency_id'               => $version->agency_id,
                'presentation_version_id' => $version->id,
                'user_id'                 => $request->user()->id,
                'override_type'           => AgentOverride::TYPE_REVIEW_TAKEOVER,
                'target_id'               => 'reviewer_user_id',
                'before_value'            => ['reviewer_user_id' => $previousUserId],
                'after_value'             => ['reviewer_user_id' => $request->user()->id],
            ]);
        });

        return response()->json([
            'ok'         => true,
            'review_url' => route('presentations.review.show', $version->id),
        ]);
    }

    // ── Internals ───────────────────────────────────────────────────────

    /** Permission gate. Throws 403 on mismatch. */
    private function authoriseReviewer(Request $request, PresentationVersion $version): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (!$user->hasPermission('access_presentations')) {
            abort(403, 'You do not have permission to access presentations.');
        }

        if ((int) $version->agency_id !== (int) $user->effectiveAgencyId()) {
            abort(403, 'Presentation is outside your agency scope.');
        }
    }

    /**
     * Drop soft-deleted comps from the included set if any were removed
     * between compile and review. Log a comp_unavailable row per dropped
     * comp so the audit trail captures the implicit change.
     *
     * Returns the number of comps that were auto-dropped, so the Blade
     * can surface a banner.
     */
    private function reconcileSoftDeletedComps(Request $request, PresentationVersion $version): int
    {
        $included = $version->included_comp_ids_json;
        if (empty($included)) return 0;

        $existing = PresentationSoldComp::query()
            ->whereIn('id', $included)
            ->whereNull('deleted_at')
            ->pluck('id')->all();
        $missing = array_diff($included, array_map('intval', $existing));
        if (empty($missing)) return 0;

        $surviving = array_values(array_diff($included, $missing));
        DB::transaction(function () use ($version, $missing, $surviving, $request) {
            $version->forceFill(['included_comp_ids_json' => $surviving])->save();
            foreach ($missing as $compId) {
                AgentOverride::create([
                    'agency_id'               => $version->agency_id,
                    'presentation_version_id' => $version->id,
                    'user_id'                 => $request->user()->id,
                    'override_type'           => AgentOverride::TYPE_COMP_UNAVAILABLE,
                    'target_id'               => (string) $compId,
                    'before_value'            => ['is_included' => true],
                    'after_value'             => ['is_included' => false, 'reason' => 'soft_deleted'],
                ]);
            }
        });
        return count($missing);
    }

    /** Mirrors MicSnapshotHydrator::resolveSubjectTitleType but on the
     *  PresentationVersion's frozen presentation. Returns null when the
     *  subject has no resolvable category — page surfaces this. */
    private function resolveSubjectTitleType(PresentationVersion $version, $presentation): ?string
    {
        $categoryName = $presentation->property?->category ?? null;
        if (!is_string($categoryName) || trim($categoryName) === '') return null;

        $row = PropertySettingItem::withoutGlobalScopes()
            ->where('agency_id', $version->agency_id)
            ->where('group', PropertySettingItem::GROUP_CATEGORY)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($categoryName))])
            ->first(['title_type']);

        return $row?->title_type;
    }

    /** Mirrors MicSnapshotHydrator::classifyCompTitleType. */
    private function classifyCompTitleType(?string $compType): string
    {
        $t = strtolower((string) $compType);
        if ($t === '') return PropertySettingItem::TITLE_OTHER;
        if (str_contains($t, 'sectional') || str_contains($t, 'apartment') || str_contains($t, 'flat')
            || str_contains($t, 'unit') || str_contains($t, 'townhouse') || str_contains($t, 'duplex')) {
            return PropertySettingItem::TITLE_SECTIONAL;
        }
        if (str_contains($t, 'vacant') || str_contains($t, 'plot') || str_contains($t, 'stand')
            || str_contains($t, 'erf')) {
            return PropertySettingItem::TITLE_VACANT;
        }
        return PropertySettingItem::TITLE_FULL;
    }
}
