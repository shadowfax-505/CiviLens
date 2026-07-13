<?php

use App\Models\Role;
use App\Models\User;

if (! function_exists('searchAdminUser')) {
    function searchAdminUser(): User
    {
        $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
