<?php

namespace App\Policies;

use App\Models\CitizenReport;
use App\Models\User;

class CitizenReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.citizen_reports_manage'));
    }

    public function viewDashboard(User $user, CitizenReport|string|null $report = null): bool
    {
        if ($user->hasRole(config('civiclens.roles.admin'))) {
            return false;
        }

        return $user->hasRole(config('civiclens.roles.citizen'))
            || $user->hasPermission(config('civiclens.permissions.reports_submit'));
    }

    public function view(User $user, CitizenReport $report): bool
    {
        return $this->viewAny($user) || $report->submitter_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.citizen'))
            || $user->hasPermission(config('civiclens.permissions.reports_submit'));
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
