<?php

namespace App\Policies;

use App\Models\User;

class AgencyPolicy extends ManageReferenceDataPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }
}
