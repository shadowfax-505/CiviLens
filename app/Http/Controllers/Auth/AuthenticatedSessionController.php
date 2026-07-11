<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Identity\AccountActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $activityLogger->log($user, 'login', $request, $user);

        if ($user->hasRole(config('civiclens.roles.admin')) && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $activityLogger->log($user, 'logout', $request, $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
