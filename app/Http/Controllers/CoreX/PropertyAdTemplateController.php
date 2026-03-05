<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use App\Models\PropertyAdTemplate;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class PropertyAdTemplateController extends Controller
{
    public function builder(PropertyAdTemplate $template = null)
    {
        if ($template) {
            $this->authorizeTemplate($template);
        }
        return view('corex.properties.ad-builder', compact('template'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'layout_json' => 'required|array',
            'is_global'   => 'boolean',
        ]);

        /** @var \App\Models\User $user */
        $user      = auth()->user();
        $scope     = PermissionService::getDataScope($user, 'properties');
        $isGlobal  = ($data['is_global'] ?? false) && $scope === 'all';

        $tpl = PropertyAdTemplate::create([
            'user_id'     => $user->id,
            'name'        => $data['name'],
            'layout_json' => $data['layout_json'],
            'is_global'   => $isGlobal,
        ]);

        return response()->json(['id' => $tpl->id, 'name' => $tpl->name]);
    }

    public function update(Request $request, PropertyAdTemplate $template)
    {
        $this->authorizeTemplate($template);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'layout_json' => 'required|array',
            'is_global'   => 'boolean',
        ]);

        /** @var \App\Models\User $user */
        $user     = auth()->user();
        $scope    = PermissionService::getDataScope($user, 'properties');
        $isGlobal = ($data['is_global'] ?? false) && $scope === 'all';

        $template->update([
            'name'        => $data['name'],
            'layout_json' => $data['layout_json'],
            'is_global'   => $isGlobal,
        ]);

        return response()->json(['id' => $template->id, 'name' => $template->name]);
    }

    public function destroy(PropertyAdTemplate $template)
    {
        $this->authorizeTemplate($template);
        $template->delete();
        return redirect()->back()->with('success', 'Template archived.');
    }

    private function authorizeTemplate(PropertyAdTemplate $template): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $scope = PermissionService::getDataScope($user, 'properties');

        if ($scope === 'all') return;
        if ((int) $template->user_id === (int) $user->id) return;

        abort(403);
    }
}
