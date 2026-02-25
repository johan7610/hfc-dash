<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Docuperfect\Document;
use App\Models\Docuperfect\DocumentType;
use App\Models\Docuperfect\Template;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $templates = Template::active()
            ->visibleTo($user)
            ->where('page_count', '>', 0)
            ->with('documentType')
            ->orderBy('name')
            ->get();

        $documents = Document::active()
            ->visibleTo($user)
            ->with(['template', 'owner'])
            ->orderByDesc('updated_at')
            ->get();

        $documentTypes = DocumentType::orderBy('sort_order')->get();

        return view('docuperfect.dashboard', compact('templates', 'documents', 'documentTypes', 'user'));
    }
}
