<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Docuperfect\FieldGroup;
use App\Models\Docuperfect\NamedField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldGroupController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $agencyId = $user->agency_id;

        $groups = FieldGroup::where(function ($q) use ($agencyId) {
                $q->where('is_global', true)
                  ->orWhere('agency_id', $agencyId);
            })
            ->orderByDesc('is_global')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Load named fields grouped by source_type for the picker
        $namedFields = NamedField::whereNull('deleted_at')
            ->orderBy('source_type')
            ->orderBy('source_contact_type')
            ->orderBy('name')
            ->get()
            ->groupBy(function ($f) {
                if ($f->source_type === 'contact') {
                    return 'contact_' . strtolower($f->source_contact_type ?? 'other');
                }
                return $f->source_type ?? 'manual';
            });

        // Pillar display config
        $pillarLabels = [
            'property'        => 'Property',
            'contact_lessor'  => 'Lessor',
            'contact_lessee'  => 'Lessee',
            'contact_seller'  => 'Seller',
            'contact_buyer'   => 'Buyer',
            'agent'           => 'Agent',
            'computed'        => 'Computed',
            'static'          => 'Static',
            'manual'          => 'Manual',
        ];

        // Pre-transform groups for the Blade view (avoid arrow functions inside @json)
        $namedFieldMap = NamedField::whereNull('deleted_at')->get()->keyBy('id');
        $groupsData = $groups->map(function ($g) use ($namedFieldMap) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'layout' => $g->layout,
                'fields' => collect($g->fields ?? [])->map(function ($f) use ($namedFieldMap) {
                    $nf = $namedFieldMap->get($f['named_field_id'] ?? null);
                    return [
                        'named_field_id' => $f['named_field_id'] ?? null,
                        'label_override' => $f['label_override'] ?? null,
                        'name' => $nf->name ?? 'Unknown',
                        'source_type' => $nf->source_type ?? '',
                        'source_contact_type' => $nf->source_contact_type ?? '',
                    ];
                })->values()->all(),
            ];
        })->values();

        return view('docuperfect.field-groups.index', compact('groups', 'groupsData', 'namedFields', 'pillarLabels'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'layout' => 'required|in:vertical,horizontal',
            'fields' => 'required|array|min:1',
            'fields.*.named_field_id' => 'required|integer|exists:docuperfect_named_fields,id',
            'fields.*.label_override' => 'nullable|string|max:255',
        ]);

        $agencyId = $user->agency_id ?? null;

        $group = FieldGroup::create([
            'agency_id' => $agencyId,
            'created_by' => $user->id,
            'name' => $request->input('name'),
            'layout' => $request->input('layout'),
            'fields' => $request->input('fields'),
            'is_global' => $agencyId === null,
            'sort_order' => (int) FieldGroup::where('agency_id', $agencyId)->max('sort_order') + 10,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['group' => $group]);
        }

        return back()->with('status', "Field group \"{$group->name}\" created.");
    }

    public function update(Request $request, FieldGroup $group)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'layout' => 'required|in:vertical,horizontal',
            'fields' => 'required|array|min:1',
            'fields.*.named_field_id' => 'required|integer|exists:docuperfect_named_fields,id',
            'fields.*.label_override' => 'nullable|string|max:255',
        ]);

        $group->update([
            'name' => $request->input('name'),
            'layout' => $request->input('layout'),
            'fields' => $request->input('fields'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['group' => $group]);
        }

        return back()->with('status', "Field group \"{$group->name}\" updated.");
    }

    public function destroy(Request $request, FieldGroup $group)
    {
        $user = $request->user();
        if (!$user->hasPermission('manage_templates')) {
            abort(403);
        }

        $name = $group->name;
        $group->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', "Field group \"{$name}\" archived.");
    }

    /**
     * JSON endpoint for the editor — returns all field groups for the current user's agency.
     */
    public function json(Request $request)
    {
        $user = $request->user();
        $agencyId = $user->agency_id;

        $groups = FieldGroup::where(function ($q) use ($agencyId) {
                $q->where('is_global', true)
                  ->orWhere('agency_id', $agencyId);
            })
            ->orderByDesc('is_global')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Resolve named field names for each group
        $namedFieldMap = NamedField::whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $result = $groups->map(function ($g) use ($namedFieldMap) {
            $resolvedFields = collect($g->fields)->map(function ($f) use ($namedFieldMap) {
                $nf = $namedFieldMap->get($f['named_field_id'] ?? null);
                $sourceGroup = 'manual';
                if ($nf) {
                    if ($nf->source_type === 'contact') {
                        $sourceGroup = 'contact_' . strtolower($nf->source_contact_type ?? 'other');
                    } else {
                        $sourceGroup = $nf->source_type ?? 'manual';
                    }
                }
                return [
                    'named_field_id' => $f['named_field_id'] ?? null,
                    'label' => $f['label_override'] ?? ($nf->name ?? 'Unknown'),
                    'source_type' => $nf->source_type ?? 'manual',
                    'source_contact_type' => $nf->source_contact_type ?? '',
                    'source_group' => $sourceGroup,
                ];
            })->values()->all();

            return [
                'id' => $g->id,
                'name' => $g->name,
                'layout' => $g->layout,
                'fields' => $resolvedFields,
            ];
        })->values()->all();

        return response()->json($result);
    }
}
