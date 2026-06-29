<?php

namespace App\Providers;

use App\Models\AdministrativeUnion;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Project;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Ward;
use App\Policies\AgencyPolicy;
use App\Policies\ManageReferenceDataPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Country::class, ManageReferenceDataPolicy::class);
        Gate::policy(Division::class, ManageReferenceDataPolicy::class);
        Gate::policy(District::class, ManageReferenceDataPolicy::class);
        Gate::policy(Upazila::class, ManageReferenceDataPolicy::class);
        Gate::policy(AdministrativeUnion::class, ManageReferenceDataPolicy::class);
        Gate::policy(Ward::class, ManageReferenceDataPolicy::class);
        Gate::policy(AgencyType::class, ManageReferenceDataPolicy::class);
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
    }
}
