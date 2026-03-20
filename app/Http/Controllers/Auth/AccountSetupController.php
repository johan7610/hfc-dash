<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountSetupController extends Controller
{
    public function show(Request $request, User $user)
    {
        // Signed URL validation is handled by the 'signed' middleware on the route.
        // If the user has already completed setup, redirect to login.
        if ($user->email_verified_at) {
            return redirect()->route('login')->with('status', 'Your account is already set up. Please sign in.');
        }

        return view('auth.account-setup', ['user' => $user]);
    }

    public function store(Request $request, User $user)
    {
        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        // Set password directly (the 'hashed' cast on User auto-hashes, so don't Hash::make)
        $user->password = $request->password;
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('login')->with('status', 'Your password has been set. You can now sign in.');
    }
}
