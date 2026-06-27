<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => (object) [],
            ], 401);
        }

        $allowedRoles = collect($roles)
            ->map(fn (string $role): ?UserRole => UserRole::tryFrom($role))
            ->filter()
            ->all();

        if (! in_array($user->role, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki akses ke resource ini.',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
