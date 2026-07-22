<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->get('admin_authenticated', false)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:255',
        ]);

        $usernameMatches = hash_equals((string) config('admin.username'), $validated['username']);
        $passwordHash = config('admin.password_hash');
        $passwordMatches = is_string($passwordHash) && password_verify($validated['password'], $passwordHash);

        if (! $usernameMatches || ! $passwordMatches) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => 'The username or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'admin_authenticated' => true,
            'admin_username' => config('admin.username'),
            'admin_authenticated_at' => now()->toIso8601String(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You have been signed out securely.');
    }
}
