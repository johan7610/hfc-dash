<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Services\Docuperfect\DocumentTemplateGenerator;
use Illuminate\Http\Request;

class DocumentImporterController extends Controller
{
    /**
     * Show the upload form.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        return view('docuperfect.importer.index');
    }

    /**
     * Accept uploaded .docx file and parse synchronously.
     * Returns JSON with redirect URL on success.
     */
    public function parse(Request $request): \Illuminate\Http\JsonResponse
    {
        \Log::info('[DocumentImporter] parse() called', [
            'has_file_document' => $request->hasFile('document'),
            'has_file_docx_file' => $request->hasFile('docx_file'),
            'all_files' => array_keys($request->allFiles()),
            'content_type' => $request->header('Content-Type'),
        ]);

        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            \Log::warning('[DocumentImporter] 403 — no manage_templates permission');
            abort(403);
        }

        // Validate — check both field names for safety
        $file = $request->file('document') ?? $request->file('docx_file');
        if (!$file) {
            \Log::error('[DocumentImporter] No file found in request');
            return response()->json([
                'error' => 'No file uploaded. Expected field name: document'
            ], 422);
        }

        if (!$file->isValid()) {
            \Log::error('[DocumentImporter] File upload invalid', [
                'error' => $file->getErrorMessage(),
            ]);
            return response()->json([
                'error' => 'Upload failed: ' . $file->getErrorMessage()
            ], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        \Log::info('[DocumentImporter] File received', [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $ext,
            'size' => $file->getSize(),
        ]);

        if ($ext !== 'docx') {
            return response()->json([
                'error' => 'Only .docx files are supported.'
            ], 422);
        }

        // Store file
        \Log::info('[DocumentImporter] Saving temp file...');
        $filename = uniqid('import_') . '.docx';
        $dir = storage_path('app/public/imports/temp');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        $file->move($dir, $filename);

        if (!file_exists($fullPath)) {
            \Log::error('[DocumentImporter] File save failed', ['path' => $fullPath]);
            return response()->json([
                'error' => 'Failed to save uploaded file.'
            ], 500);
        }

        \Log::info('[DocumentImporter] File saved', [
            'path' => $fullPath,
            'size' => filesize($fullPath),
        ]);

        // Parse synchronously
        try {
            \Log::info('[DocumentImporter] Calling DocxParserService...');
            $parser = new \App\Services\Docuperfect\DocxParserService();
            $result = $parser->parse($fullPath);

            \Log::info('[DocumentImporter] Parse returned', [
                'html_length' => strlen($result['html'] ?? ''),
                'field_count' => count($result['fields'] ?? []),
                'warnings' => $result['warnings'] ?? [],
            ]);

            // Store in session for review() and generate()
            \Log::info('[DocumentImporter] Storing in session...');
            session([
                'import_html'     => $result['html'],
                'import_fields'   => $result['fields'],
                'import_filename' => $file->getClientOriginalName(),
            ]);

            // Clean up temp file
            @unlink($fullPath);

            $redirect = route('docuperfect.import.review');
            \Log::info('[DocumentImporter] SUCCESS — returning redirect', [
                'redirect' => $redirect,
            ]);

            return response()->json([
                'success'  => true,
                'redirect' => $redirect,
                'warnings' => $result['warnings'] ?? [],
            ]);

        } catch (\Throwable $e) {
            @unlink($fullPath);

            \Log::error('[DocumentImporter] EXCEPTION in parse', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Render the review view from session data.
     */
    public function review(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $html     = session('import_html');
        $fields   = session('import_fields', []);
        $filename = session('import_filename', 'Document');

        if (empty($html)) {
            return redirect()
                ->route('docuperfect.import.index')
                ->with('error',
                    'No parsed document found. ' .
                    'Please upload again.');
        }

        return view('docuperfect.importer.review', [
            'parsed' => [
                'html'              => $html,
                'fields'            => $fields,
                'template_name'     => pathinfo(
                    $filename,
                    PATHINFO_FILENAME
                ),
                'original_filename' => $filename,
            ],
            'templateName' => pathinfo($filename, PATHINFO_FILENAME),
            'fields' => $fields,
        ]);
    }

    /**
     * Generate a template from confirmed field mappings.
     */
    public function generate(Request $request, DocumentTemplateGenerator $generator)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $importData = [
            'html' => session('import_html'),
            'template_name' => session('import_filename', 'Document'),
        ];

        if (empty($importData['html'])) {
            return redirect()->route('docuperfect.import.index')
                ->with('error', 'No import data found. Please upload a document first.');
        }

        $request->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'fields' => ['required', 'array'],
            'fields.*.key' => ['required', 'string'],
            'fields.*.label' => ['required', 'string'],
            'fields.*.pillar' => ['required', 'string'],
            'fields.*.assigned_to' => ['required', 'string', 'in:agent,lessor,lessee,buyer,seller'],
            'fields.*.field_type' => ['nullable', 'string', 'in:text,date,number'],
            'edited_html' => ['nullable', 'string'],
        ]);

        $fieldMappings = $request->input('fields');
        $templateName = $request->input('template_name', $importData['template_name']);

        // Use edited HTML from the editor if provided, otherwise fall back to session HTML
        $parsedData = [
            'html' => $request->filled('edited_html')
                ? $request->input('edited_html')
                : ($importData['html'] ?? ''),
        ];

        $template = $generator->generate(
            $parsedData,
            $fieldMappings,
            $templateName,
            $user->id
        );

        // Clear session data
        session()->forget(['import_html', 'import_fields', 'import_filename']);

        return redirect()->route('docuperfect.templates.index')
            ->with('success', 'Template "' . $template->name . '" imported successfully.');
    }
}
