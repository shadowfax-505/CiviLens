<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasRole(config('civiclens.roles.staff'))
            || $user->hasPermission(config('civiclens.permissions.documents_manage'));
    }

    public function view(User $user, Document $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function update(User $user, Document $document): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function archive(User $user, Document $document): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->hasRole(config('civiclens.roles.admin'));
    }

    public function download(User $user, Document $document): bool
    {
        return $this->viewAny($user);
    }
}
