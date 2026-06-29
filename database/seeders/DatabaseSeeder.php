<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Country;
use App\Models\Permission;
use App\Models\PermissionGroup;
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
        $accessGroup = PermissionGroup::query()->firstOrCreate(
            ['slug' => 'access-management'],
            ['name' => 'Access Management', 'description' => 'Identity, users, roles, and permissions.'],
        );
        $projectGroup = PermissionGroup::query()->firstOrCreate(
            ['slug' => 'civic-data'],
            ['name' => 'Civic Data', 'description' => 'Projects, analytics, and civic reports.'],
        );
        $registryGroup = PermissionGroup::query()->firstOrCreate(
            ['slug' => 'registry-management'],
            ['name' => 'Registry Management', 'description' => 'Geographic references and government agency registry.'],
        );

        $permissions = collect([
            ['permission_group_id' => $accessGroup->id, 'name' => 'Manage Users', 'slug' => config('civiclens.permissions.users_manage'), 'description' => 'Manage platform users and access.'],
            ['permission_group_id' => $accessGroup->id, 'name' => 'Manage Roles', 'slug' => config('civiclens.permissions.roles_manage'), 'description' => 'Manage roles and permission assignments.'],
            ['permission_group_id' => $registryGroup->id, 'name' => 'Manage Locations', 'slug' => config('civiclens.permissions.locations_manage'), 'description' => 'Manage normalized geographic reference data.'],
            ['permission_group_id' => $registryGroup->id, 'name' => 'Manage Agencies', 'slug' => config('civiclens.permissions.agencies_manage'), 'description' => 'Manage government agency records and assignments.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Projects', 'slug' => config('civiclens.permissions.projects_manage'), 'description' => 'Create and update civic project records.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'View Analytics', 'slug' => config('civiclens.permissions.analytics_view'), 'description' => 'View platform analytics dashboards.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Submit Reports', 'slug' => config('civiclens.permissions.reports_submit'), 'description' => 'Submit civic reports and field updates.'],
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
        $staff->permissions()->sync($permissions->whereIn('slug', [
            config('civiclens.permissions.agencies_manage'),
            config('civiclens.permissions.locations_manage'),
            config('civiclens.permissions.projects_manage'),
            config('civiclens.permissions.analytics_view'),
        ])->pluck('id'));
        $citizen->permissions()->sync($permissions->where('slug', config('civiclens.permissions.reports_submit'))->pluck('id'));

        $baselineAdmin = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'locked_at' => null,
            ],
        );

        $baselineAdmin->roles()->sync([$admin->id]);

        $bangladesh = Country::query()->firstOrCreate(
            ['iso2' => 'BD'],
            [
                'name' => 'Bangladesh',
                'iso3' => 'BGD',
                'phone_code' => '+880',
                'latitude' => 23.685,
                'longitude' => 90.3563,
            ],
        );

        $ministry = AgencyType::query()->firstOrCreate(
            ['slug' => 'ministry'],
            ['name' => 'Ministry', 'description' => 'National government ministry.'],
        );
        AgencyType::query()->firstOrCreate(
            ['slug' => 'department'],
            ['name' => 'Department', 'description' => 'Department or directorate under a parent agency.'],
        );
        AgencyType::query()->firstOrCreate(
            ['slug' => 'regional-office'],
            ['name' => 'Regional Office', 'description' => 'Regional office for subnational operations.'],
        );
        AgencyType::query()->firstOrCreate(
            ['slug' => 'local-office'],
            ['name' => 'Local Office', 'description' => 'Local office serving a defined geography.'],
        );

        Agency::query()->firstOrCreate(
            ['slug' => 'ministry-of-planning'],
            [
                'agency_type_id' => $ministry->id,
                'country_id' => $bangladesh->id,
                'name' => 'Ministry of Planning',
                'short_name' => 'MoP',
                'status' => 'active',
                'description' => 'Baseline agency for CivicLens reference data.',
            ],
        );
    }
}
