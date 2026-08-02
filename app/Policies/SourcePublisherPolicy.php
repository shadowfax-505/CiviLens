<?php

namespace App\Policies;

use App\Models\SourcePublisher;
use App\Models\User;

class SourcePublisherPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, SourcePublisher $publisher): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, SourcePublisher $publisher): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.sources_manage'));
    }
}
