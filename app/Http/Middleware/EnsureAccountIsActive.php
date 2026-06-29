<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAccessApplication()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(Response::HTTP_FORBIDDEN, 'This account cannot access CivicLens.');
        }

        return $next($request);
    }
}
