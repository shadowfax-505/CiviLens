<?php

namespace App\Policies;

use App\Models\User;

class ProcurementPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.procurements_manage'));
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
