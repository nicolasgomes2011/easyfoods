<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role) {
            abort(403, 'Acesso não autorizado.');
        }

        if (isset($user->is_active) && ! $user->is_active) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['email' => 'Sua conta foi desativada.']);
        }

        $allowed = array_map(
            fn (string $r) => UserRole::from($r),
            $roles
        );

        if (! in_array($user->role, $allowed)) {
            abort(403, 'Você não tem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
