<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdminAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->get('superadmin_authenticated', false)) {
            return redirect()->route('superadmin.login')->withErrors([
                'authentication' => 'Please sign in to access the Superadmin dashboard.',
            ]);
        }

        return $next($request);
    }
}
