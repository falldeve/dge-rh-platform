<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Le super-admin voit et accède à tout.
        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403);
        }

        return $next($request);
    }
}
