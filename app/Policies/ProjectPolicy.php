<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasRole(config('civiclens.roles.staff'))
            || $user->hasPermission(config('civiclens.permissions.projects_manage'));
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function archive(User $user, Project $project): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }
}
