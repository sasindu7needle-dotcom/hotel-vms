<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        // Superadmin has a separate portal. Never let its elevated session
        // open Admin pages, where it could bypass a system user's permissions.
        if ($request->session()->get('superadmin_authenticated', false)) {
            return redirect()->route('superadmin.dashboard');
        }

        if (! $request->session()->get('admin_authenticated', false)) {
            return redirect()->route('admin.login')->withErrors([
                'authentication' => 'Please sign in to access the admin panel.',
            ]);
        }

        // A user can be suspended or removed after signing in. Re-check the
        // account on each protected request so the change takes effect at once.
        $userId = $request->session()->get('admin_user_id');
        if ($userId) {
            $user = User::find($userId);

            if (! $user || strtolower((string) $user->status) !== 'active') {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('admin.login')->withErrors([
                    'authentication' => 'Your account is no longer active. Please contact an administrator.',
                ]);
            }

            $request->session()->put([
                'admin_username' => $user->username ?: $user->name,
                'admin_role' => $user->role,
                'admin_permissions' => is_array($user->permissions) ? $user->permissions : [],
            ]);
        }

        $perms = $request->session()->get('admin_permissions');
        if (! is_array($perms)) {
            $perms = [];
        }

        // Accounts created in User Access Management must have at least one
        // explicitly granted page. The env-based fallback remains unrestricted.
        if ($userId && empty($perms)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'authentication' => 'Your account has no page access assigned. Please contact an administrator.',
            ]);
        }

        if (! $userId && empty($perms)) {
            return $next($request);
        }

        $routeName = $request->route() ? (string) $request->route()->getName() : '';

        $routePermissionMap = [
            'admin.dashboard' => 'Dashboard',
            'admin.visitors' => 'Visitors',
            'admin.receipts' => 'Receipt Manager',
            'admin.configurations.event' => 'Event Configurations',
            'admin.configurations.capacity' => 'Occupancy Limit',
            'admin.configurations.categories' => 'Visitor Categories',
            'admin.configurations.users' => 'Users & Access',
        ];

        foreach ($routePermissionMap as $prefix => $requiredPerm) {
            if (str_starts_with($routeName, $prefix)) {
                // Existing Visitor-access accounts can use the receipt workflow
                // without needing their saved permissions re-created.
                $receiptAccess = $requiredPerm === 'Receipt Manager' && in_array('Visitors', $perms);
                if (! in_array($requiredPerm, $perms) && ! $receiptAccess) {
                    return $this->redirectToFirstAllowedRoute($perms);
                }
            }
        }

        return $next($request);
    }

    private function redirectToFirstAllowedRoute(array $perms)
    {
        $routeMap = [
            'Dashboard' => 'admin.dashboard',
            'Visitors' => 'admin.visitors.index',
            'Receipt Manager' => 'admin.receipts.index',
            'Event Configurations' => 'admin.configurations.event.edit',
            'Occupancy Limit' => 'admin.configurations.capacity.edit',
            'Visitor Categories' => 'admin.configurations.categories.index',
            'Users & Access' => 'admin.configurations.users.index',
        ];

        foreach ($routeMap as $perm => $routeName) {
            if (in_array($perm, $perms)) {
                return redirect()->route($routeName)->withErrors([
                    'access' => 'You do not have permission to view that page.',
                ]);
            }
        }

        return redirect()->route('admin.login');
    }
}
