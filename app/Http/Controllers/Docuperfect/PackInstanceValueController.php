<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Docuperfect\PackInstanceValue;
use Illuminate\Http\Request;

class PackInstanceValueController extends Controller
{
    public function show(Request $request, $instanceId)
    {
        $values = PackInstanceValue::where('pack_instance_id', $instanceId)
            ->get()
            ->pluck('value', 'named_field_id');

        return response()->json($values);
    }

    public function save(Request $request)
    {
        $request->validate([
            'pack_instance_id' => 'required|integer',
            'named_field_id' => 'required|integer|exists:docuperfect_named_fields,id',
            'value' => 'nullable|string',
        ]);

        PackInstanceValue::updateOrCreate(
            [
                'pack_instance_id' => $request->input('pack_instance_id'),
                'named_field_id' => $request->input('named_field_id'),
            ],
            [
                'value' => $request->input('value'),
            ]
        );

        return response()->json(['ok' => true]);
    }
}
