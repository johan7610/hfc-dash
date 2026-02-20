<?php

namespace App\Http\Controllers\Presentation;

use App\Domain\Presentation\UploadProcessor;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MarketAnalyticsRun;
use App\Models\Presentation;
use App\Models\PresentationSnapshot;
use App\Models\PresentationUpload;
use App\Models\SaleProbabilityRun;
use App\Services\MarketAnalytics\Adapters\ImportedListingsAdapter;
use App\Services\MarketAnalytics\Adapters\InternalDealsAdapter;
use App\Services\MarketAnalytics\DTOs\MarketAnalyticsInput;
use App\Services\MarketAnalytics\Helpers\InputHasher;
use App\Services\MarketAnalytics\MarketAnalyticsService;
use App\Services\SaleProbability\DTOs\SaleProbabilityInput;
use App\Services\SaleProbability\SaleProbabilityService;
use Illuminate\Http\Request;

class PresentationController extends Controller
{
    public function index()
    {
        $uploads  = PresentationUpload::latest()->get();
        $branches = Branch::orderBy('name')->get();

        // Ensure the scaffold presentation row exists so forms have a valid FK target.
        // Uses the first available branch; if none exist, $presentation stays null and
        // the view hides the forms gracefully.
        $firstBranch  = $branches->first();
        $presentation = null;
        if ($firstBranch) {
            $presentation = Presentation::firstOrCreate(
                ['title' => 'Scaffold'],
                [
                    'branch_id'          => $firstBranch->id,
                    'created_by_user_id' => auth()->id(),
                    'status'             => 'draft',
                    'currency'           => 'ZAR',
                ]
            );
        }

        $snapshots = PresentationSnapshot::with(['presentation'])
            ->latest()
            ->limit(10)
            ->get();

        return view('presentations.index', compact('uploads', 'branches', 'presentation', 'snapshots'));
    }

    public function create()
    {
        // Stub — to be implemented in a future task
        return view('presentations.index', ['uploads' => collect(), 'branches' => collect(), 'presentation' => null]);
    }

    public function show(int $presentation)
    {
        // Stub — to be implemented in a future task
        return view('presentations.index', ['uploads' => collect(), 'branches' => collect(), 'presentation' => null]);
    }

    public function store(Request $request)
    {
        // Stub — to be implemented in a future task
        return redirect()->route('presentations.index');
    }

    /**
     * Handle a document upload for a presentation.
     * Stores file, extracts text, and detects structured fields.
     * Never touches finance logic.
     */
    public function upload(Request $request, Presentation $presentation)
    {
        $request->validate([
            'document' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $processor = new UploadProcessor(new \App\Domain\Presentation\TextExtractionService());
        $processor->process($request->file('document'), $presentation, auth()->id());

        return redirect()->route('presentations.index')
            ->with('success', 'File uploaded and processed.');
    }

    /**
     * Run market analytics + sale probability for a presentation.
     * Validates inputs, calls both services, returns results view.
     */
    public function compute(Request $request, Presentation $presentation)
    {
        $validated = $request->validate([
            'suburb'        => ['required', 'string', 'max:100'],
            'type'          => ['required', 'string', 'in:house,unit,land,other'],
            'price'         => ['nullable', 'numeric', 'min:0'],
            'size_m2'       => ['nullable', 'integer', 'min:0'],
            'bedrooms'      => ['nullable', 'integer', 'min:0', 'max:20'],
            'period_months' => ['required', 'integer', 'in:6,12,24'],
            'branch_id'     => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $maInput = new MarketAnalyticsInput(
            suburb:          $validated['suburb'],
            propertyType:    $validated['type'],
            periodMonths:    (int) $validated['period_months'],
            bedrooms:        isset($validated['bedrooms']) ? (int) $validated['bedrooms'] : null,
            sourceBranchId:  isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            subjectSizeM2:   isset($validated['size_m2']) ? (int) $validated['size_m2'] : null,
            subjectPriceInc: isset($validated['price']) ? (float) $validated['price'] : null,
        );

        $maService = new MarketAnalyticsService(
            new InternalDealsAdapter(),
            new ImportedListingsAdapter(),
        );

        $maResult = $maService->run($maInput);

        // Retrieve the MA run that was just persisted (by stable inputs hash)
        $inputsHash = InputHasher::hash($maInput);
        $maRun      = MarketAnalyticsRun::where('inputs_hash', $inputsHash)->latest()->first();

        // Run SP service to capture sensitivity array + tag created_by
        $spInput = new SaleProbabilityInput(
            marketAnalyticsRunId:        $maRun->id,
            marketAnalyticsModelVersion: MarketAnalyticsService::MODEL_VERSION,
            marketAnalyticsInputsHash:   $inputsHash,
            marketAnalyticsResult:       $maResult,
        );

        $spResult = (new SaleProbabilityService())->run($spInput, auth()->id());

        // Retrieve the SP run that was just persisted (latest for this MA run)
        $spRun = SaleProbabilityRun::where('market_analytics_run_id', $maRun->id)
            ->latest()
            ->first();

        $branches = Branch::orderBy('name')->get();

        return view('presentations.compute', [
            'presentation' => $presentation,
            'maResult'     => $maResult,
            'spResult'     => $spResult,
            'maRun'        => $maRun,
            'spRun'        => $spRun,
            'inputs'       => $validated,
            'branches'     => $branches,
        ]);
    }
}
