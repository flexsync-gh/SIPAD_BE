<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userRole = $user->user_role instanceof UserRole ? $user->user_role->value : (string) $user->user_role;

        // Admin (Full / Master Admin) has access to all admin and lecturer operations
        if ($userRole === UserRole::Admin->value) {
            return $next($request);
        }

        $allowedRoles = [];
        foreach ($roles as $role) {
            $normalized = UserRole::normalize($role);
            if ($normalized) {
                $allowedRoles[] = $normalized;
            }
            if (in_array(strtolower($role), ['master_admin', 'master admin', 'admin'], true)) {
                $allowedRoles[] = UserRole::Admin->value;
            }
            if (in_array(strtolower($role), ['dosen', 'lecturer'], true)) {
                $allowedRoles[] = UserRole::Lecturer->value;
            }
        }

        if (in_array($userRole, $allowedRoles, true)) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengakses resource ini.',
        ], 403);
    }
}
