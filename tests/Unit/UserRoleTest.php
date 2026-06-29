<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_check_assigned_role(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'description' => 'Platform administrator',
        ]);

        $user->roles()->attach($role);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('staff'));
    }

    public function test_user_can_check_permission_through_role(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff',
            'description' => 'Government staff user',
        ]);
        $permission = Permission::query()->create([
            'name' => 'Manage Projects',
            'slug' => 'projects.manage',
            'description' => 'Create and update project records',
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('projects.manage'));
        $this->assertFalse($user->hasPermission('system.configure'));
    }
}
