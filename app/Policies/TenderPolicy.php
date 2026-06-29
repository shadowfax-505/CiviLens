<?php

namespace App\Policies;

use App\Models\Tender;
use App\Models\User;

class TenderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.procurements_manage'));
    }

    public function view(User $user, Tender $tender): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Tender $tender): bool
    {
        return $this->viewAny($user);
    }

    public function archive(User $user, Tender $tender): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Tender $tender): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Tender $tender): bool
    {
        return false;
    }
}
