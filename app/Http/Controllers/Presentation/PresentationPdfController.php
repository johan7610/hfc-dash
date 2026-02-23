<?php

namespace App\Http\Controllers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use App\Models\PresentationVersion;
use App\Services\Presentations\PresentationPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the HTML presentation pack for a compiled version (P18).
 *
 * Feature-flagged via config('features.presentation_pdf_v1').
 * When the flag is off the route returns 404 so the UI can hide the button.
 */
class PresentationPdfController extends Controller
{
    public function __construct(private readonly PresentationPdfService $pdfService) {}

    /**
     * Serve the pack HTML inline in the browser for viewing/printing.
     *
     * GET /presentations/{presentation}/versions/{version}/pdf
     */
    public function download(Request $request, Presentation $presentation, PresentationVersion $version): Response
    {
        abort_unless(config('features.presentation_pdf_v1', false), 404);

        // Ensure the version belongs to this presentation
        abort_if($version->presentation_id !== $presentation->id, 404);

        $path = $this->pdfService->storagePath($version);

        // Regenerate if missing
        if (!Storage::disk(PresentationPdfService::STORAGE_DISK)->exists($path)) {
            $path = $this->pdfService->generate($version);
        }

        $html = Storage::disk(PresentationPdfService::STORAGE_DISK)->get($path);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
