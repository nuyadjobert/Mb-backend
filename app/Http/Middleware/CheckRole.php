<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Usage in routes: ->middleware('role:admin,manager')
     * Only App\Models\User accounts (admin/manager) have roles.
     * Branch (store) tokens never pass this check.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}