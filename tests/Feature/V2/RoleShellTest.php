<?php

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function roleShellUser(string $roleSlug, array $permissions = []): User
{
    $group = PermissionGroup::query()->firstOrCreate(['slug' => 'shell-test'], ['name' => 'Shell test']);
    $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => str($roleSlug)->headline()->toString()]);

    foreach ($permissions as $slug) {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->headline()->toString(), 'permission_group_id' => $group->id],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

it('renders an administrator console shell', function (): void {
    $admin = roleShellUser(config('civiclens.roles.admin'));

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-role-shell="administrator"', false)
        ->assertSee('Administration');
});

it('renders a staff operations shell distinct from the administrator console', function (): void {
    $staff = roleShellUser(config('civiclens.roles.staff'));

    $this->actingAs($staff)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-role-shell="staff"', false)
        ->assertSee('Staff workspace')
        ->assertDontSee('data-role-shell="administrator"', false);
});

it('renders a citizen shell for report-submit users', function (): void {
    $citizen = roleShellUser(config('civiclens.roles.citizen'));

    $this->actingAs($citizen)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-role-shell="citizen"', false)
        ->assertSee('My CivicLens');
});
