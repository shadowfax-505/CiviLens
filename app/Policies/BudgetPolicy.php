<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasRole(config('civiclens.roles.staff'))
            || $user->hasPermission(config('civiclens.permissions.budgets_manage'));
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function viewCitizenDashboard(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.citizen'));
    }

    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function archive(User $user, Budget $budget): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function restore(User $user, Budget $budget): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function delete(User $user, Budget $budget): bool
    {
        return false;
    }
}
