<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles Valid entries from UserRole Enum cases
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Validate token abilities mapping
        foreach ($roles as $role) {
            if ($user->tokenCan("role:{$role}")) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Forbidden. Your current role does not grant access to this endpoint.'
        ], 403);
    }
}
