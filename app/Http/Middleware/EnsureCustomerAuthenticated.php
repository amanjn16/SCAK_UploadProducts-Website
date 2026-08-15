<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            if ($request->expectsJson()) {
                abort(401, 'Authentication required.');
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
