<?php

namespace App\Services\Identity;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAdministrationService
{
    public function __construct(private readonly AccountActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $roleIds
     */
    public function createUser(array $attributes, array $roleIds, User $actor, Request $request): User
    {
        $user = User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);

        $user->forceFill(['email_verified_at' => null])->save();

        $roles = Role::query()->whereIn('id', $roleIds)->pluck('id')->all();
        if ($roles !== []) {
            $user->roles()->sync($roles);
        }

        $this->activityLogger->log($user, 'admin.created', $request, $actor, [
            'roles' => array_values($roles),
            'email_verified_at' => null,
        ]);

        return $user;
    }

    public function updateStatus(User $user, bool $isActive, User $actor, Request $request): void
    {
        if (! $isActive) {
            $this->ensureNotFinalActiveVerifiedAdministrator($user);
        }

        $user->forceFill(['is_active' => $isActive])->save();

        $this->activityLogger->log($user, $isActive ? 'admin.activated' : 'admin.deactivated', $request, $actor);
    }

    public function updateLock(User $user, bool $locked, User $actor, Request $request): void
    {
        if ($locked) {
            $this->ensureNotFinalActiveVerifiedAdministrator($user);
        }

        $user->forceFill(['locked_at' => $locked ? now() : null])->save();

        $this->activityLogger->log($user, $locked ? 'admin.locked' : 'admin.unlocked', $request, $actor);
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function syncRoles(User $user, array $roleIds, User $actor, Request $request): void
    {
        $adminRoleId = Role::query()->where('slug', config('civiclens.roles.admin'))->value('id');

        if ($adminRoleId !== null && ! in_array((int) $adminRoleId, array_map('intval', $roleIds), true)) {
            $this->ensureNotFinalActiveVerifiedAdministrator($user);
        }

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

    private function ensureNotFinalActiveVerifiedAdministrator(User $user): void
    {
        if (! $user->hasRole(config('civiclens.roles.admin')) || ! $user->is_active || $user->isLocked() || $user->email_verified_at === null) {
            return;
        }

        $remaining = User::query()
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->whereNull('locked_at')
            ->whereNotNull('email_verified_at')
            ->whereHas('roles', fn ($query) => $query->where('slug', config('civiclens.roles.admin')))
            ->exists();

        if (! $remaining) {
            throw ValidationException::withMessages([
                'user' => 'The final active verified administrator cannot be changed.',
            ]);
        }
    }
}
