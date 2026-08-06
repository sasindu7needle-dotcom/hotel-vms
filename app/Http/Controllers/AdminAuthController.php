<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        // Let a Superadmin deliberately switch to a restricted system-user
        // account from this page instead of reusing their elevated session.
        if ($request->session()->get('admin_authenticated', false)
            && ! $request->session()->get('superadmin_authenticated', false)) {
            return $this->redirectToFirstAllowedRoute($request);
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:255',
        ]);

        $input = trim($validated['username']);
        $password = $validated['password'];

        // 1. Check database users first
        $user = User::query()
            ->where(function ($query) use ($input) {
                $normalizedInput = strtolower($input);

                $query->whereRaw('LOWER(username) = ?', [$normalizedInput])
                    ->orWhereRaw('LOWER(email) = ?', [$normalizedInput])
                    // Keeps accounts created before usernames were introduced usable.
                    ->orWhereNull('username')->whereRaw('LOWER(name) = ?', [$normalizedInput]);
            })
            ->first();

        if ($user) {
            if (strtolower((string) $user->status) !== 'active') {
                return back()->withInput($request->only('username'))->withErrors([
                    'username' => 'This user account is suspended.',
                ]);
            }

            if (! Hash::check($password, $user->password)) {
                return back()->withInput($request->only('username'))->withErrors([
                    'username' => 'The email/username or password is incorrect.',
                ]);
            }

            $user->update(['last_login_at' => now()]);

            $permissions = is_array($user->permissions) ? $user->permissions : [];

            $request->session()->regenerate();
            $request->session()->forget([
                'superadmin_authenticated',
                'superadmin_username',
                'superadmin_authenticated_at',
            ]);
            $request->session()->put([
                'admin_authenticated' => true,
                'admin_user_id' => $user->id,
                'admin_username' => $user->username ?: $user->name,
                'admin_role' => $user->role,
                'admin_permissions' => $permissions,
                'admin_authenticated_at' => now()->toIso8601String(),
            ]);

            return $this->redirectToFirstAllowedRoute($request);
        }

        // 2. Check env admin fallback
        $usernameMatches = hash_equals((string) config('admin.username'), $input);
        $passwordHash = config('admin.password_hash');
        $passwordMatches = is_string($passwordHash) && password_verify($password, $passwordHash);

        if ($usernameMatches && $passwordMatches) {
            $request->session()->regenerate();
            $request->session()->forget([
                'superadmin_authenticated',
                'superadmin_username',
                'superadmin_authenticated_at',
            ]);
            $request->session()->put([
                'admin_authenticated' => true,
                'admin_username' => config('admin.username'),
                'admin_role' => 'Administrator',
                'admin_permissions' => ['Dashboard', 'Visitors', 'Attendance Summary', 'Attendance Detail', 'Revenue Summary', 'Revenue Detail', 'Event Configurations', 'Occupancy Limit', 'Visitor Categories', 'Users & Access'],
                'admin_authenticated_at' => now()->toIso8601String(),
            ]);

            return redirect()->route('admin.dashboard');
        }

        return back()->withInput($request->only('username'))->withErrors([
            'username' => 'The email/username or password is incorrect.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You have been signed out securely.');
    }

    private function redirectToFirstAllowedRoute(Request $request)
    {
        $perms = $request->session()->get('admin_permissions', []);
        $isSuperadmin = $request->session()->get('superadmin_authenticated', false);

        if ($isSuperadmin || empty($perms) || in_array('Dashboard', $perms)) {
            return redirect()->route('admin.dashboard');
        }

        $routeMap = [
            'Visitors' => 'admin.visitors.index',
            'Attendance Summary' => 'admin.attendance.summary',
            'Attendance Detail' => 'admin.attendance.detail',
            'Revenue Summary' => 'admin.revenue.summary',
            'Revenue Detail' => 'admin.revenue.detail',
            'Event Configurations' => 'admin.configurations.event.edit',
            'Occupancy Limit' => 'admin.configurations.capacity.edit',
            'Visitor Categories' => 'admin.configurations.categories.index',
            'Users & Access' => 'admin.configurations.users.index',
        ];

        foreach ($routeMap as $perm => $routeName) {
            if (in_array($perm, $perms)) {
                return redirect()->route($routeName);
            }
        }

        return redirect()->route('admin.dashboard');
    }
}
