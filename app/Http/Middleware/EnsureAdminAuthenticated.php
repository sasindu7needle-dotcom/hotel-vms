<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->get('admin_authenticated', false)) {
            return redirect()->route('admin.login')->withErrors([
                'authentication' => 'Please sign in to access the admin dashboard.',
            ]);
        }

        return $next($request);
    }
}
