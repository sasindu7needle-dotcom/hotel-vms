<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SuperAdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->get('superadmin_authenticated', false)) {
            return redirect()->route('superadmin.dashboard');
        }

        return view('superadmin.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:255',
        ]);

        $usernameMatches = hash_equals((string) config('admin.superadmin_username'), $validated['username']);
        $passwordHash = config('admin.superadmin_password_hash');
        $passwordMatches = is_string($passwordHash) && password_verify($validated['password'], $passwordHash);

        if (! $usernameMatches || ! $passwordMatches) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => 'The superadmin username or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'superadmin_authenticated' => true,
            'superadmin_username' => config('admin.superadmin_username'),
            'superadmin_authenticated_at' => now()->toIso8601String(),
        ]);

        return redirect()->intended(route('superadmin.dashboard'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget([
            'superadmin_authenticated',
            'superadmin_username',
            'superadmin_authenticated_at',
        ]);
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login')->with('status', 'You have been signed out of the Superadmin Portal.');
    }
}
