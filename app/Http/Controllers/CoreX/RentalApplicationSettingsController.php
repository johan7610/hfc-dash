<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\DocumentType;
use App\Models\RentalApplication;
use App\Models\RentalApplicationChecklistConfig;
use App\Models\RentalApplicationDeclineEmailSetting;
use App\Models\RentalApplicationDocumentRequirement;
use App\Models\RentalApplicationQualifyingSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AT-392 spec §5 — agency-configurable supporting-document checklist per
 * employment type. Nothing here is ever enforced at submission time; this
 * only drives what shows as "outstanding" on a returned application.
 */
class RentalApplicationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $agencyId = $request->user()->effectiveAgencyId();
        $documentTypes = DocumentType::where('is_active', true)->orderBy('sort_order')->get();

        $checklists = [];
        $isConfigured = [];
        foreach (RentalApplication::EMPLOYMENT_TYPES as $type) {
            $checklists[$type] = RentalApplicationDocumentRequirement::checklistFor($agencyId, $type)
                ->pluck('id')->all();
            // Surfaced in the view so the screen is honest about what it's
            // showing — "your saved (possibly empty) list" vs "the V8
            // default, not yet saved" — not just correct in the data.
            $isConfigured[$type] = RentalApplicationChecklistConfig::where('agency_id', $agencyId)
                ->where('employment_type', $type)->exists();
        }

        // AT-392 Phase 2 — Johan: "qualifying formula - agency can set this."
        // Reuses this SAME settings screen rather than a second settings home.
        $qualifyingMultiplier = RentalApplicationQualifyingSetting::multiplierFor($agencyId);

        // AT-392 authoriser flow — Johan: "ro then co approval process...
        // Both configured as agency settings, multi-select from users,
        // exactly like the existing CO and RO settings." Same query shape
        // as settings.blade.php's own FICA MLRO section ($agencyUsers there).
        $agencyUsers = User::where('agency_id', $agencyId)
            ->where('is_active', true)->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name', 'email', 'role', 'branch_id']);
        $agency = Agency::find($agencyId);
        $roUserIds = $agency?->rental_application_ro_user_ids ?? [];
        $coUserIds = $agency?->rental_application_co_user_ids ?? [];

        // AT-392 authoriser flow — Johan: "each agency will want their own
        // wording on declined." Suggested default shown until the agency
        // saves their own — see RentalApplicationDeclineEmailSetting.
        $declineEmail = RentalApplicationDeclineEmailSetting::forAgency($agencyId);

        return view('corex.settings.rental-applications', compact(
            'documentTypes', 'checklists', 'isConfigured', 'qualifyingMultiplier', 'agencyUsers', 'roUserIds', 'coUserIds', 'declineEmail'
        ));
    }

    /**
     * AT-392 authoriser flow — separate route/method, same reasoning as
     * updateQualifyingFormula() above.
     */
    public function updateDeclineEmail(Request $request)
    {
        $agencyId = $request->user()->effectiveAgencyId();

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        RentalApplicationDeclineEmailSetting::updateOrCreate(
            ['agency_id' => $agencyId],
            [
                'subject' => ($validated['subject'] ?? '') !== '' ? $validated['subject'] : null,
                'body' => ($validated['body'] ?? '') !== '' ? $validated['body'] : null,
            ],
        );

        return redirect()->route('corex.settings.rental-applications.edit')
            ->with('success', 'Decline email wording saved.');
    }

    /**
     * AT-392 authoriser flow — RO tier. Separate route/method, same
     * reasoning as updateQualifyingFormula() above.
     */
    public function updateRO(Request $request)
    {
        $agencyId = $request->user()->effectiveAgencyId();

        $validated = $request->validate([
            'rental_application_ro_user_ids' => ['nullable', 'array'],
            'rental_application_ro_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = $validated['rental_application_ro_user_ids'] ?? [];

        Agency::whereKey($agencyId)->update([
            'rental_application_ro_user_ids' => ! empty($ids) ? array_map('intval', $ids) : null,
        ]);

        return redirect()->route('corex.settings.rental-applications.edit')
            ->with('success', 'Reviewers saved.');
    }

    /**
     * AT-392 authoriser flow — CO tier.
     */
    public function updateCO(Request $request)
    {
        $agencyId = $request->user()->effectiveAgencyId();

        $validated = $request->validate([
            'rental_application_co_user_ids' => ['nullable', 'array'],
            'rental_application_co_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = $validated['rental_application_co_user_ids'] ?? [];

        Agency::whereKey($agencyId)->update([
            'rental_application_co_user_ids' => ! empty($ids) ? array_map('intval', $ids) : null,
        ]);

        return redirect()->route('corex.settings.rental-applications.edit')
            ->with('success', 'Overrides saved.');
    }

    /**
     * AT-392 Phase 2 — separate route/method from update() above so a save
     * here can never interfere with the document-checklist form's own
     * all-3-types-at-once submission shape.
     */
    public function updateQualifyingFormula(Request $request)
    {
        $agencyId = $request->user()->effectiveAgencyId();

        // RA-02 (cc5 re-test, Round 8) — same sanitizer as every other
        // numeric money field on this feature; this one is a ratio rather
        // than a rand amount, but agents type it the same way.
        $request->merge(RentalApplication::sanitizeNumericInput(
            $request->only(['income_to_rent_multiplier']),
            ['income_to_rent_multiplier'],
        ));

        $validated = $request->validate([
            'income_to_rent_multiplier' => ['required', 'numeric', 'min:0.1', 'max:99.99'],
        ]);

        RentalApplicationQualifyingSetting::updateOrCreate(
            ['agency_id' => $agencyId],
            ['income_to_rent_multiplier' => $validated['income_to_rent_multiplier']],
        );

        return redirect()->route('corex.settings.rental-applications.edit')
            ->with('success', 'Qualifying formula saved.');
    }

    public function update(Request $request)
    {
        $agencyId = $request->user()->effectiveAgencyId();

        $validated = $request->validate([
            'checklists' => ['nullable', 'array'],
            'checklists.*' => ['array'],
            'checklists.*.*' => ['integer', 'exists:document_types,id'],
        ]);

        foreach (RentalApplication::EMPLOYMENT_TYPES as $type) {
            $documentTypeIds = $validated['checklists'][$type] ?? [];

            RentalApplicationDocumentRequirement::where('agency_id', $agencyId)
                ->where('employment_type', $type)
                ->delete();

            foreach (array_values($documentTypeIds) as $sortOrder => $documentTypeId) {
                RentalApplicationDocumentRequirement::create([
                    'agency_id' => $agencyId,
                    'employment_type' => $type,
                    'document_type_id' => $documentTypeId,
                    'sort_order' => $sortOrder,
                ]);
            }

            // Johan, 2026-09-07 — this form always submits all 3 employment
            // types together, so every save marks all 3 "configured," even
            // one that ends up with zero items selected. That zero-item save
            // IS the agency's deliberate choice and must never be confused
            // with "never touched this screen" (see RentalApplicationDocument
            // Requirement::checklistFor()). firstOrCreate — a type already
            // marked configured from a prior save is left as-is, not
            // duplicated.
            RentalApplicationChecklistConfig::firstOrCreate([
                'agency_id' => $agencyId,
                'employment_type' => $type,
            ]);
        }

        return redirect()->route('corex.settings.rental-applications.edit')
            ->with('success', 'Rental application document checklist saved.');
    }
}
