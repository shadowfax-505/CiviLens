<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function create(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $this->viewAny($user);
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $this->viewAny($user);
    }

    public function administer(User $user, User $model): bool
    {
        return $this->viewAny($user) && $user->id !== $model->id;
    }
}
