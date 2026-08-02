<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdateDistrictPreferencesRequest;
use App\Http\Requests\Profile\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\District;
use App\Services\Identity\AccountActivityLogger;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = AuthenticatedUser::from($request);
        $activities = $user
            ->accountActivities()
            ->latest()
            ->limit(20)
            ->get();

        return view('profile.show', [
            'user' => $user,
            'activities' => $activities,
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'selectedDistrictIds' => $user->preferredDistricts()->pluck('districts.id')->all(),
            'notificationCategories' => config('civiclens.notifications.categories', []),
        ]);
    }

    public function update(UpdateProfileRequest $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);
        $user->fill($request->safe()->only(['name', 'email']))->save();

        $activityLogger->log($user, 'profile.updated', $request, $user);

        return redirect()->route('settings.show')->with('status', 'profile-updated');
    }

    public function updateAvatar(UpdateAvatarRequest $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        $activityLogger->log($user, 'avatar.updated', $request, $user);

        return redirect()->route('settings.show')->with('status', 'avatar-updated');
    }

    public function updateNotifications(UpdateNotificationPreferencesRequest $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);
        $current = $user->notification_preferences;
        if (! is_array($current)) {
            $current = [];
        }
        $categories = config('civiclens.notifications.categories', []);
        $categoryPreferences = [];

        foreach ($categories as $category => $definition) {
            $enabled = $request->has($category)
                ? $request->boolean($category)
                : (bool) ($current[$category] ?? $definition['default']);
            $categoryPreferences[$category] = $enabled;
            $user->notificationPreferences()->updateOrCreate(
                ['category' => $category],
                ['enabled' => $enabled],
            );
        }

        $user->forceFill([
            'notification_preferences' => array_replace($current, [
                'email_reports' => $request->boolean('email_reports'),
                'security_alerts' => $request->boolean('security_alerts'),
                'appearance' => $request->validated('appearance', 'system'),
            ], $categoryPreferences),
        ])->save();

        $activityLogger->log($user, 'notifications.updated', $request, $user);

        return redirect()->route('settings.show')->with('status', 'notifications-updated');
    }

    public function updateDistricts(UpdateDistrictPreferencesRequest $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);
        $user->preferredDistricts()->sync($request->validated('district_ids', []));

        $activityLogger->log($user, 'district-preferences.updated', $request, $user);

        return redirect()->route('settings.show')->with('status', 'district-preferences-updated');
    }

    public function updatePassword(UpdatePasswordRequest $request, AccountActivityLogger $activityLogger): RedirectResponse
    {
        $user = AuthenticatedUser::from($request);
        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'password_changed_at' => now(),
        ])->save();

        $activityLogger->log($user, 'password.updated', $request, $user);

        return redirect()->route('settings.show')->with('status', 'password-updated');
    }
}
