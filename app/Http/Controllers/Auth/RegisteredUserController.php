<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\AccountActivityLogger;
use App\Services\Identity\EmailVerificationOtpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, AccountActivityLogger $activityLogger, EmailVerificationOtpService $otp): RedirectResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));

        $citizen = Role::query()->where('slug', config('civiclens.roles.citizen'))->first();
        if ($citizen !== null) {
            $user->roles()->syncWithoutDetaching([$citizen->id]);
        }

        event(new Registered($user));
        $otp->send($user);

        Auth::login($user);
        $activityLogger->log($user, 'registered', $request, $user);

        return redirect()->route('verification.notice');
    }
}
