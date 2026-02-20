<?php

namespace App\Http\Controllers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\MarketAnalyticsRun;
use App\Models\Presentation;
use App\Models\PresentationSnapshot;
use App\Models\SaleProbabilityRun;
use Illuminate\Http\Request;

class PresentationSnapshotController extends Controller
{
    /**
     * Validate inputs, create an immutable snapshot row, and redirect to the snapshot view.
     */
    public function saveSnapshot(Request $request, Presentation $presentation)
    {
        $validated = $request->validate([
            'market_run_id'       => ['required', 'integer', 'exists:market_analytics_runs,id'],
            'prob_run_id'         => ['required', 'integer', 'exists:sale_probability_runs,id'],
            'inputs_json'         => ['required', 'string'],
            'output_summary_json' => ['required', 'string'],
        ]);

        $snapshot = PresentationSnapshot::create([
            'presentation_id'         => $presentation->id,
            'generated_by_user_id'    => auth()->id(),
            'created_by_user_id'      => auth()->id(),
            'market_analytics_run_id' => $validated['market_run_id'],
            'sale_probability_run_id' => $validated['prob_run_id'],
            'inputs_json'             => $validated['inputs_json'],
            'output_summary_json'     => $validated['output_summary_json'],
            'snapshot_json'           => '{}',
            'generated_at'            => now(),
        ]);

        return redirect()->route('presentations.snapshots.show', [$presentation, $snapshot])
            ->with('success', 'Snapshot saved.');
    }

    /**
     * Display a saved snapshot. Ensures the snapshot belongs to the given presentation.
     */
    public function showSnapshot(Presentation $presentation, PresentationSnapshot $snapshot)
    {
        abort_if($snapshot->presentation_id !== $presentation->id, 404);

        $maRun = $snapshot->market_analytics_run_id
            ? MarketAnalyticsRun::find($snapshot->market_analytics_run_id)
            : null;

        $spRun = $snapshot->sale_probability_run_id
            ? SaleProbabilityRun::find($snapshot->sale_probability_run_id)
            : null;

        return view('presentations.snapshot', compact('presentation', 'snapshot', 'maRun', 'spRun'));
    }
}
