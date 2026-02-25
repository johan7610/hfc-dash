<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgeDocument;
use App\Services\AI\DocumentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeController extends Controller
{
    public function index()
    {
        $categories = KnowledgeCategory::ordered()
            ->withCount('documents')
            ->get();

        $recentDocuments = KnowledgeDocument::with(['category', 'uploader'])
            ->latest()
            ->limit(20)
            ->get();

        $stats = [
            'total_documents' => KnowledgeDocument::count(),
            'total_chunks' => \App\Models\KnowledgeChunk::count(),
            'ellie_enabled' => KnowledgeDocument::where('is_ellie_enabled', true)->count(),
            'by_status' => [
                'ready' => KnowledgeDocument::where('status', 'ready')->count(),
                'processing' => KnowledgeDocument::where('status', 'processing')->count(),
                'error' => KnowledgeDocument::where('status', 'error')->count(),
            ],
        ];

        return view('admin.knowledge.index', compact('categories', 'recentDocuments', 'stats'));
    }

    public function show($categoryId)
    {
        $category = KnowledgeCategory::findOrFail($categoryId);

        $documents = KnowledgeDocument::where('category_id', $categoryId)
            ->with('uploader')
            ->latest()
            ->paginate(20);

        $allCategories = KnowledgeCategory::ordered()->get();

        return view('admin.knowledge.category', compact('category', 'documents', 'allCategories'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:knowledge_categories,id',
            'file' => 'required|file|mimes:pdf,docx,doc,txt,md|max:20480',
            'description' => 'nullable|string|max:2000',
            'version' => 'nullable|string|max:50',
        ]);

        $service = new DocumentProcessingService();
        $document = $service->processUpload(
            $request->file('file'),
            $request->input('category_id'),
            auth()->id(),
            $request->input('title'),
            $request->input('description'),
            $request->input('version'),
        );

        $statusMsg = $document->status === 'ready'
            ? "Document uploaded and processed successfully ({$document->chunk_count} chunks)."
            : "Document uploaded but processing encountered an issue: {$document->error_message}";

        return redirect()->back()->with('status', $statusMsg);
    }

    public function toggleActive($documentId)
    {
        $document = KnowledgeDocument::findOrFail($documentId);
        $document->is_active = !$document->is_active;
        $document->save();

        return redirect()->back()->with('status', "Document '{$document->title}' " . ($document->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function toggleEllie($documentId)
    {
        $document = KnowledgeDocument::findOrFail($documentId);
        $document->is_ellie_enabled = !$document->is_ellie_enabled;
        $document->save();

        return redirect()->back()->with('status', "Ellie access for '{$document->title}' " . ($document->is_ellie_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function reprocess($documentId)
    {
        $document = KnowledgeDocument::findOrFail($documentId);
        $service = new DocumentProcessingService();
        $service->reprocess($document);

        $document->refresh();
        $statusMsg = $document->status === 'ready'
            ? "Document reprocessed successfully ({$document->chunk_count} chunks)."
            : "Reprocessing issue: {$document->error_message}";

        return redirect()->back()->with('status', $statusMsg);
    }

    public function destroy($documentId)
    {
        $document = KnowledgeDocument::findOrFail($documentId);
        $title = $document->title;

        // Delete the file from storage
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        // Chunks are cascade-deleted by the DB
        $document->delete();

        return redirect()->back()->with('status', "Document '{$title}' deleted.");
    }

    public function preview($documentId)
    {
        $document = KnowledgeDocument::with(['chunks' => function ($q) {
            $q->orderBy('chunk_index');
        }, 'category', 'uploader'])->findOrFail($documentId);

        return view('admin.knowledge.preview', compact('document'));
    }
}
