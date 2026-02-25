<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Docuperfect\Document;
use App\Models\Docuperfect\DocumentType;
use App\Models\Docuperfect\Pack;
use App\Models\Docuperfect\Template;
use Illuminate\Http\Request;

class PackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $packs = Pack::visibleTo($user)
            ->with(['templates', 'branches', 'owner'])
            ->orderBy('name')
            ->get();

        $canManage = $user->isAdmin() || $user->isBranchManager();

        return view('docuperfect.packs.index', compact('packs', 'canManage', 'user'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isBranchManager()) {
            abort(403);
        }

        $templates = Template::active()->visibleTo($user)
            ->with('documentType')
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::orderBy('sort_order')->get();
        $branches = Branch::orderBy('name')->get();

        return view('docuperfect.packs.create', compact('templates', 'documentTypes', 'branches', 'user'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isBranchManager()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_global' => 'boolean',
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'exists:docuperfect_templates,id',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        $pack = Pack::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_global' => $request->boolean('is_global'),
            'owner_id' => $user->id,
        ]);

        // Attach templates with sort order
        $templateIds = $request->input('template_ids');
        $sync = [];
        foreach ($templateIds as $i => $tid) {
            $sync[$tid] = ['sort_order' => $i * 10];
        }
        $pack->templates()->sync($sync);

        // Attach branches
        if (!$request->boolean('is_global') && $request->has('branch_ids')) {
            $pack->branches()->sync($request->input('branch_ids'));
        }

        return redirect()->route('docuperfect.packs.index')
            ->with('status', "Pack \"{$pack->name}\" created.");
    }

    public function edit(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isBranchManager()) {
            abort(403);
        }

        $pack = Pack::with(['templates', 'branches'])->findOrFail($id);

        $templates = Template::active()->visibleTo($user)
            ->with('documentType')
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::orderBy('sort_order')->get();
        $branches = Branch::orderBy('name')->get();

        return view('docuperfect.packs.create', compact('pack', 'templates', 'documentTypes', 'branches', 'user'));
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isBranchManager()) {
            abort(403);
        }

        $pack = Pack::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_global' => 'boolean',
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'exists:docuperfect_templates,id',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        $pack->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_global' => $request->boolean('is_global'),
        ]);

        // Sync templates with sort order
        $templateIds = $request->input('template_ids');
        $sync = [];
        foreach ($templateIds as $i => $tid) {
            $sync[$tid] = ['sort_order' => $i * 10];
        }
        $pack->templates()->sync($sync);

        // Sync branches
        if ($request->boolean('is_global')) {
            $pack->branches()->detach();
        } elseif ($request->has('branch_ids')) {
            $pack->branches()->sync($request->input('branch_ids'));
        }

        return redirect()->route('docuperfect.packs.index')
            ->with('status', "Pack \"{$pack->name}\" updated.");
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isBranchManager()) {
            abort(403);
        }

        $pack = Pack::findOrFail($id);
        $name = $pack->name;
        $pack->delete();

        return redirect()->route('docuperfect.packs.index')
            ->with('status', "Pack \"{$name}\" deleted.");
    }

    public function launch(Request $request, $id)
    {
        $user = $request->user();
        $pack = Pack::visibleTo($user)->with('templates')->findOrFail($id);

        // Generate a unique pack instance ID (timestamp-based)
        $packInstanceId = (int) (microtime(true) * 1000);

        $count = 0;
        foreach ($pack->templates as $template) {
            // Clear field values but keep field definitions
            $fields = $template->fields_json ?? [];
            $clearedFields = array_map(function ($field) {
                $f = $field;
                if (isset($f['value'])) {
                    $f['value'] = '';
                }
                if (isset($f['selectedValue'])) {
                    $f['selectedValue'] = '';
                }
                if (isset($f['active'])) {
                    $f['active'] = false;
                }
                if (isset($f['text'])) {
                    $f['text'] = '';
                }
                return $f;
            }, $fields);

            Document::create([
                'name' => $pack->name . ' — ' . $template->name,
                'template_id' => $template->id,
                'fields_json' => $clearedFields,
                'owner_id' => $user->id,
                'branch_id' => $user->effectiveBranchId(),
                'pack_instance_id' => $packInstanceId,
            ]);
            $count++;
        }

        return redirect()->route('docuperfect.documents.index', ['pack_instance' => $packInstanceId])
            ->with('status', "Created {$count} document" . ($count !== 1 ? 's' : '') . " from \"{$pack->name}\".");
    }
}
