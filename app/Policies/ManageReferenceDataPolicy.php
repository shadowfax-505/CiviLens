<?php

namespace App\Policies;

use App\Models\User;

class ManageReferenceDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }
}
