<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Docuperfect\FieldCorrection;
use App\Models\Docuperfect\ImportDraft;
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

            // Auto-cleanup: delete drafts older than 24 hours for this user
            ImportDraft::where('user_id', $user->id)
                ->where('created_at', '<', now()->subHours(24))
                ->delete();

            // Store in database instead of session — survives session expiry
            $claudeOriginals = collect($result['fields'])->map(fn($f, $i) => [
                'index' => $i,
                'context' => $f['context'] ?? '',
                'suggested_key' => $f['suggested_key'] ?? '',
                'suggested_label' => $f['suggested_label'] ?? '',
            ])->toArray();

            $draft = ImportDraft::create([
                'user_id'     => $user->id,
                'filename'    => $file->getClientOriginalName(),
                'html'        => $result['html'],
                'fields_json' => json_encode([
                    'fields'           => $result['fields'],
                    'claude_originals' => $claudeOriginals,
                ]),
            ]);

            // Store only the draft ID in session (tiny — won't expire easily)
            session(['import_draft_id' => $draft->id]);

            // Clean up temp file
            @unlink($fullPath);

            $redirect = route('docuperfect.import.review');
            \Log::info('[DocumentImporter] SUCCESS — draft saved to DB', [
                'draft_id' => $draft->id,
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
     * Render the review view from database draft.
     */
    public function review(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $draft = $this->loadDraft($user->id);

        if (!$draft) {
            return redirect()
                ->route('docuperfect.import.index')
                ->with('error',
                    'No parsed document found. ' .
                    'Please upload again.');
        }

        $fieldsData = json_decode($draft->fields_json, true) ?? [];
        $fields = $fieldsData['fields'] ?? [];
        $filename = $draft->filename;

        return view('docuperfect.importer.review', [
            'parsed' => [
                'html'              => $draft->html,
                'fields'            => $fields,
                'template_name'     => pathinfo($filename, PATHINFO_FILENAME),
                'original_filename' => $filename,
            ],
            'templateName' => pathinfo($filename, PATHINFO_FILENAME),
            'fields' => $fields,
            'draftId' => $draft->id,
        ]);
    }

    /**
     * Generate a template from confirmed field mappings.
     */
    public function generate(Request $request, DocumentTemplateGenerator $generator)
    {
        \Log::info('DocumentImporter: generate() called', ['request_all' => $request->keys()]);

        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        // Load draft from database — survives session expiry
        $draftId = $request->input('draft_id') ?? session('import_draft_id');
        $draft = $draftId
            ? ImportDraft::where('id', $draftId)->where('user_id', $user->id)->first()
            : null;

        if (!$draft) {
            \Log::warning('DocumentImporter: no draft found', ['draft_id' => $draftId]);
            return redirect()->route('docuperfect.import.index')
                ->with('error', 'No import data found. Please upload a document first.');
        }

        \Log::info('DocumentImporter: draft loaded from DB', [
            'draft_id' => $draft->id,
            'html_length' => strlen($draft->html),
        ]);

        try {
            $validated = $request->validate([
                'template_name' => ['required', 'string', 'max:255'],
                'fields' => ['nullable', 'array'],
                'fields.*.key' => ['required', 'string'],
                'fields.*.label' => ['required', 'string'],
                'fields.*.pillar' => ['required', 'string'],
                'fields.*.assigned_to' => ['required', 'string', 'in:agent,lessor,lessee,buyer,seller,property,skip,manual'],
                'fields.*.field_type' => ['nullable', 'string', 'in:text,date,number'],
                'fields.*.correction_reason' => ['nullable', 'string', 'max:500'],
                'edited_html' => ['nullable', 'string'],
                'draft_id' => ['nullable', 'integer'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('DocumentImporter: validation FAILED', [
                'errors' => $e->errors(),
                'fields_sample' => array_slice($request->input('fields', []), 0, 3),
            ]);
            throw $e;
        }

        \Log::info('DocumentImporter: validation passed', [
            'fields_count' => count($validated['fields']),
            'first_field' => $validated['fields'][0] ?? 'EMPTY',
            'template_name' => $validated['template_name'] ?? 'EMPTY',
        ]);

        $fieldMappings = $validated['fields'];
        $templateName = $request->input('template_name', $draft->filename);

        // Use edited HTML from the editor if provided, otherwise fall back to draft HTML
        $parsedData = [
            'html' => $request->filled('edited_html')
                ? $request->input('edited_html')
                : $draft->html,
        ];

        $template = $generator->generate(
            $parsedData,
            $fieldMappings,
            $templateName,
            $user->id
        );

        // Save signing parties selection
        $signingParties = $request->input('signing_parties', ['lessor', 'lessee', 'agent']);
        $template->update(['signing_parties' => $signingParties]);

        // Log field corrections — compare user's final assignments against Claude's originals
        $fieldsData = json_decode($draft->fields_json, true) ?? [];
        $claudeOriginals = $fieldsData['claude_originals'] ?? [];

        $this->logFieldCorrections(
            $fieldMappings,
            $claudeOriginals,
            $templateName,
            $user->id
        );

        // Soft-delete the draft — no longer needed
        $draft->delete();

        // Clear session draft reference
        session()->forget('import_draft_id');

        return redirect()->route('docuperfect.import.index')
            ->with('success', 'Template "' . $template->name . '" created successfully.');
    }

    /**
     * Load the current user's import draft from the database.
     */
    protected function loadDraft(int $userId): ?ImportDraft
    {
        $draftId = session('import_draft_id');

        if ($draftId) {
            $draft = ImportDraft::where('id', $draftId)
                ->where('user_id', $userId)
                ->first();
            if ($draft) return $draft;
        }

        // Fallback: find the user's most recent draft
        return ImportDraft::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Compare user's final field assignments against Claude's original suggestions.
     * Store corrections so future imports learn from them.
     */
    protected function logFieldCorrections(array $fieldMappings, array $claudeOriginals, string $documentType, int $userId): void
    {
        foreach ($fieldMappings as $idx => $userField) {
            $original = $claudeOriginals[$idx] ?? null;
            if (!$original) continue;

            $claudeKey = $original['suggested_key'] ?? '';
            $claudeLabel = $original['suggested_label'] ?? '';
            $userKey = $userField['key'] ?? '';
            $userLabel = $userField['label'] ?? '';
            $context = $original['context'] ?? '';

            // Skip if Claude had no suggestion or user didn't change it
            if (empty($claudeKey) || $claudeKey === $userKey) continue;

            \Log::info('DocxParser: Field correction', [
                'context' => $context,
                'claude_suggested' => $claudeLabel . ' (' . $claudeKey . ')',
                'user_corrected_to' => $userLabel . ' (' . $userKey . ')',
                'document_type' => $documentType,
            ]);

            FieldCorrection::create([
                'context' => mb_substr($context, 0, 500),
                'claude_suggested_key' => $claudeKey,
                'claude_suggested_label' => $claudeLabel,
                'user_corrected_key' => $userKey,
                'user_corrected_label' => $userLabel,
                'correction_reason' => $userField['correction_reason'] ?? null,
                'document_type' => $documentType,
                'user_id' => $userId,
            ]);
        }
    }
}
