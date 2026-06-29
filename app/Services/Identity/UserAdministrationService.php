<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAdministrationService
{
    public function __construct(private readonly AccountActivityLogger $activityLogger) {}

    public function updateStatus(User $user, bool $isActive, User $actor, Request $request): void
    {
        $user->forceFill(['is_active' => $isActive])->save();

        $this->activityLogger->log($user, $isActive ? 'admin.activated' : 'admin.deactivated', $request, $actor);
    }

    public function updateLock(User $user, bool $locked, User $actor, Request $request): void
    {
        $user->forceFill(['locked_at' => $locked ? now() : null])->save();

        $this->activityLogger->log($user, $locked ? 'admin.locked' : 'admin.unlocked', $request, $actor);
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function syncRoles(User $user, array $roleIds, User $actor, Request $request): void
    {
        $user->roles()->sync($roleIds);

        $this->activityLogger->log($user, 'admin.roles.updated', $request, $actor, ['roles' => array_values($roleIds)]);
    }

    public function setTemporaryPassword(User $user, string $password, User $actor, Request $request): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'password_changed_at' => now(),
        ])->save();

        $this->activityLogger->log($user, 'admin.password.reset', $request, $actor);
    }
}
