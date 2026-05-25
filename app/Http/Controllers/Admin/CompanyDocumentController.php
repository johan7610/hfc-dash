<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 9c-3 — admin CRUD for company documents (privacy policy, T&Cs, …).
 */
final class CompanyDocumentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess();
        $agencyId = $this->agencyId();

        $documents = CompanyDocument::forAgency($agencyId)
            ->orderBy('document_type')
            ->get();

        return view('admin.company-documents.index', [
            'documents'    => $documents,
            'documentTypes'=> CompanyDocument::TYPES,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAccess();
        $validated = $request->validate([
            'document_type' => 'required|string|in:' . implode(',', array_keys(CompanyDocument::TYPES)),
        ]);
        $agencyId = $this->agencyId();

        $existing = CompanyDocument::forAgency($agencyId)
            ->ofType($validated['document_type'])
            ->whereNull('deleted_at')
            ->first();
        if ($existing) {
            return redirect()->route('admin.company-documents.edit', $existing->id)
                ->with('info', 'A document of this type already exists — editing the existing one.');
        }

        $doc = CompanyDocument::create([
            'agency_id'              => $agencyId,
            'document_type'          => $validated['document_type'],
            'title'                  => CompanyDocument::TYPES[$validated['document_type']],
            'content'                => '',
            'content_format'         => 'markdown',
            'is_published'           => false,
            'last_updated_by_user_id'=> Auth::id(),
        ]);

        return redirect()->route('admin.company-documents.edit', $doc->id);
    }

    public function edit(int $id)
    {
        $this->authorizeAccess();
        $doc = $this->findOrFail($id);

        return view('admin.company-documents.edit', [
            'document' => $doc,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeAccess();
        $doc = $this->findOrFail($id);

        $validated = $request->validate([
            'title'   => 'required|string|max:200',
            'content' => 'nullable|string|max:200000',
            'publish' => 'sometimes|boolean',
        ]);

        $doc->title   = $validated['title'];
        $doc->content = $validated['content'] ?? '';
        $doc->last_updated_by_user_id = Auth::id();

        // Publish toggle on save — explicit publish action vs. save-draft.
        if ($request->boolean('publish')) {
            $doc->is_published = true;
            $doc->published_at = $doc->published_at ?? now();
        }
        $doc->save();

        return redirect()->route('admin.company-documents.edit', $doc->id)
            ->with('success', $request->boolean('publish') ? 'Saved + published.' : 'Draft saved.');
    }

    public function togglePublished(int $id)
    {
        $this->authorizeAccess();
        $doc = $this->findOrFail($id);

        $doc->is_published = !$doc->is_published;
        if ($doc->is_published && !$doc->published_at) {
            $doc->published_at = now();
        }
        $doc->last_updated_by_user_id = Auth::id();
        $doc->save();

        return redirect()->route('admin.company-documents.index')
            ->with('success', $doc->is_published ? "{$doc->title} published." : "{$doc->title} unpublished.");
    }

    public function destroy(int $id)
    {
        $this->authorizeAccess();
        $doc = $this->findOrFail($id);
        $doc->delete();
        return redirect()->route('admin.company-documents.index')
            ->with('success', 'Document archived.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && (
            $user->hasPermission('manage_information_officer') ||
            method_exists($user, 'isEffectiveOwner') && $user->isEffectiveOwner()
        ), 403);
    }

    private function agencyId(): int
    {
        $id = Auth::user()?->effectiveAgencyId();
        abort_if($id === null, 403, 'No agency context.');
        return (int) $id;
    }

    private function findOrFail(int $id): CompanyDocument
    {
        $doc = CompanyDocument::forAgency($this->agencyId())
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();
        abort_if(!$doc, 404);
        return $doc;
    }
}
