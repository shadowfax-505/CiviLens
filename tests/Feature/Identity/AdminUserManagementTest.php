<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    $adminRole = Role::query()->create(['name' => 'Administrator', 'slug' => 'admin']);
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->roles()->attach($adminRole);

    return $admin;
}

it('restricts user administration to administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
});

it('lets administrators search filter sort and paginate users', function (): void {
    $admin = createAdminUser();
    User::factory()->create(['name' => 'Active Staff', 'email' => 'staff@example.com', 'is_active' => true]);
    User::factory()->create(['name' => 'Inactive Citizen', 'email' => 'citizen@example.com', 'is_active' => false]);

    $this->actingAs($admin)->get('/admin/users?search=staff&status=active&sort=email&direction=asc')
        ->assertOk()
        ->assertSee('staff@example.com')
        ->assertDontSee('citizen@example.com');
});

it('lets administrators activate deactivate lock users and manage roles', function (): void {
    $admin = createAdminUser();
    $staffRole = Role::query()->create(['name' => 'Staff', 'slug' => 'staff']);
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patch("/admin/users/{$user->id}/status", [
        'is_active' => '0',
    ])->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/admin/users/{$user->id}/lock", [
        'locked' => '1',
    ])->assertRedirect();
    expect($user->fresh()->locked_at)->not->toBeNull();

    $this->actingAs($admin)->put("/admin/users/{$user->id}/roles", [
        'roles' => [$staffRole->id],
    ])->assertRedirect();
    expect($user->fresh()->hasRole('staff'))->toBeTrue();
});

it('lets administrators set a temporary password', function (): void {
    $admin = createAdminUser();
    $user = User::factory()->create();

    $this->actingAs($admin)->put("/admin/users/{$user->id}/password", [
        'password' => 'TempSecurePass123!',
        'password_confirmation' => 'TempSecurePass123!',
    ])->assertRedirect();

    expect(password_verify('TempSecurePass123!', $user->fresh()->password))->toBeTrue();
});
