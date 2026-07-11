<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasRole(config('civiclens.roles.staff'))
            || $user->hasPermission(config('civiclens.permissions.contractors_manage'));
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function archive(User $user, Organization $organization): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }
}
