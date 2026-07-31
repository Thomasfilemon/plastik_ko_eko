<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfNotAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        // Public auth pages/actions — do not redirect these
        if ($request->is('signin', 'login', 'logout')) {
            return $next($request);
        }

        if (!Auth::check()) {
            return redirect()->route('signin');
        }

        return $next($request);
    }
}
