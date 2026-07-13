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

        $requiresVerifiedEmail = $user?->hasRole(config('civiclens.roles.admin')) === true;

        if ($user !== null && (! $user->canAccessApplication() || ($requiresVerifiedEmail && ! $user->hasVerifiedEmail()))) {
            if ($requiresVerifiedEmail && ! $user->hasVerifiedEmail() && $request->routeIs('verification.notice', 'verification.verify', 'verification.send', 'logout')) {
                return $next($request);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(Response::HTTP_FORBIDDEN, 'This account cannot access CivicLens until it is active, unlocked, and verified.');
        }

        return $next($request);
    }
}
