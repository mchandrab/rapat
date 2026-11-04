<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi route ke role yang diizinkan; dicek di server, bukan hanya menyembunyikan menu.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
