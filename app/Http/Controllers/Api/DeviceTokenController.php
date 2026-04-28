<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'platform'    => 'required|in:ios,android,web',
            'token'       => 'required|string|max:512',
            'app_version' => 'sometimes|nullable|string|max:32',
        ]);

        $user = $request->user();

        DeviceToken::updateOrCreate(
            ['user_id' => $user->id, 'token' => $data['token']],
            [
                'platform'     => $data['platform'],
                'app_version'  => $data['app_version'] ?? null,
                'last_seen_at' => now(),
                'deleted_at'   => null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $token)
    {
        $user = $request->user();
        DeviceToken::where('user_id', $user->id)->where('token', $token)->delete();
        return response()->json(['ok' => true]);
    }
}
