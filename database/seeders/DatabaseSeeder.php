<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'description' => 'Manage platform users and access.'],
            ['name' => 'Manage Projects', 'slug' => 'projects.manage', 'description' => 'Create and update civic project records.'],
            ['name' => 'View Analytics', 'slug' => 'analytics.view', 'description' => 'View platform analytics dashboards.'],
            ['name' => 'Submit Reports', 'slug' => 'reports.submit', 'description' => 'Submit civic reports and field updates.'],
        ])->map(fn (array $permission) => Permission::query()->firstOrCreate(
            ['slug' => $permission['slug']],
            $permission,
        ));

        $admin = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'Platform administrator with full operational access.'],
        );
        $staff = Role::query()->firstOrCreate(
            ['slug' => 'staff'],
            ['name' => 'Government Staff', 'description' => 'Staff user who maintains civic data.'],
        );
        $citizen = Role::query()->firstOrCreate(
            ['slug' => 'citizen'],
            ['name' => 'Citizen', 'description' => 'Public user who can browse and submit civic reports.'],
        );

        $admin->permissions()->sync($permissions->pluck('id'));
        $staff->permissions()->sync($permissions->whereIn('slug', ['projects.manage', 'analytics.view'])->pluck('id'));
        $citizen->permissions()->sync($permissions->where('slug', 'reports.submit')->pluck('id'));

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->roles()->sync([$admin->id]);
    }
}
