<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.budgets_manage'));
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function archive(User $user, Budget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Budget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return false;
    }
}
