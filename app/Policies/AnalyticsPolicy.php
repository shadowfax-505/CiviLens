<?php

namespace App\Policies;

use App\Models\User;

class AnalyticsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.analytics_view'))
            || $user->hasPermission(config('civiclens.permissions.analytics_manage'));
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.analytics_manage'));
    }

    public function update(User $user): bool
    {
        return $this->create($user);
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
