<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\CommandCenter\AutomationRule;
use App\Models\CommandCenter\DocumentExpectation;
use App\Models\CommandCenter\ReminderDefault;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Settings overview page.
     */
    public function index()
    {
        $automationRules = AutomationRule::orderBy('sort_order')->get();
        $docExpectations = DocumentExpectation::orderBy('property_type')->orderBy('sort_order')->get();
        $reminderDefaults = ReminderDefault::orderBy('event_category')->get();

        return view('command-center.settings.index', [
            'automationRules'  => $automationRules,
            'docExpectations'  => $docExpectations,
            'reminderDefaults' => $reminderDefaults,
        ]);
    }

    /**
     * Toggle an automation rule on/off.
     */
    public function toggleRule(AutomationRule $rule)
    {
        $rule->update(['is_active' => !$rule->is_active]);

        return back()->with('success', "Rule \"{$rule->name}\" " . ($rule->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Store a document expectation.
     */
    public function storeExpectation(Request $request)
    {
        $request->validate([
            'property_type'    => 'required|string|max:50',
            'label'            => 'required|string|max:255',
            'due_offset_hours' => 'required|integer|min:1',
            'required'         => 'nullable|boolean',
        ]);

        DocumentExpectation::create($request->all());

        return back()->with('success', 'Document expectation added.');
    }

    /**
     * Delete a document expectation.
     */
    public function destroyExpectation(DocumentExpectation $expectation)
    {
        $expectation->delete();
        return back()->with('success', 'Document expectation removed.');
    }

    /**
     * Store/update a reminder default.
     */
    public function storeReminderDefault(Request $request)
    {
        $request->validate([
            'event_category'     => 'required|string|max:80',
            'reminder_offsets'   => 'required|string',
            'escalation_enabled' => 'nullable|boolean',
            'escalation_delay'   => 'nullable|integer|min:1',
            'escalation_to'      => 'nullable|in:bm,admin,both',
        ]);

        // Parse comma-separated offsets (in days) into minutes
        $daysInput = array_map('trim', explode(',', $request->reminder_offsets));
        $offsetMinutes = array_map(fn ($d) => (int) $d * 1440, $daysInput);

        ReminderDefault::updateOrCreate(
            ['event_category' => $request->event_category],
            [
                'reminder_offsets'   => $offsetMinutes,
                'escalation_enabled' => $request->boolean('escalation_enabled'),
                'escalation_delay'   => $request->escalation_delay ?? 1440,
                'escalation_to'      => $request->escalation_to ?? 'bm',
            ]
        );

        return back()->with('success', 'Reminder defaults saved.');
    }
}
