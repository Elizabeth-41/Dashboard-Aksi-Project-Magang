<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute ke role tertentu, mis. `role:admin_pusat` atau
 * `role:unit,admin_pusat`.
 */
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

        if ($user === null) {
            abort(401);
        }

        $allowedRoles = array_map(
            fn (string $role) => UserRole::from($role),
            $roles,
        );

        if (! in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Anda tidak memiliki akses untuk aksi ini.');
        }

        return $next($request);
    }
}
