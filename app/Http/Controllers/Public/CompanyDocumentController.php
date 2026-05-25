<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\CompanyDocument;
use Illuminate\Support\Str;

/**
 * Phase 9c-3 — public renderer for legal documents.
 *
 * GET /legal/{token} — no auth, no agency middleware. 404 unless the
 * document is published AND the token matches. Renders the content
 * (markdown → HTML or raw HTML based on stored format) inside a clean
 * agency-branded layout.
 */
final class CompanyDocumentController extends Controller
{
    public function show(string $token)
    {
        $doc = CompanyDocument::withoutGlobalScopes()
            ->where('public_token', $token)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$doc, 404);
        abort_unless($doc->is_published, 404);

        $agency = Agency::withoutGlobalScopes()->find($doc->agency_id);
        abort_if(!$agency, 404);

        $renderedHtml = $doc->content_format === 'html'
            ? (string) $doc->content
            : (string) Str::markdown((string) $doc->content);

        return view('public.legal-document', [
            'document'     => $doc,
            'agency'       => $agency,
            'renderedHtml' => $renderedHtml,
            'logoUrl'      => $agency->logo_path ? asset('storage/' . $agency->logo_path) : null,
        ]);
    }
}
