<?php

namespace App\Providers;

use App\Events\BudgetCreated;
use App\Events\ContractAwarded;
use App\Events\DocumentUploaded;
use App\Events\ProjectCreated;
use App\Events\TenderPublished;
use App\Listeners\LogDomainEvent;
use App\Models\AdministrativeUnion;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Budget;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Project;
use App\Models\Tender;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Ward;
use App\Policies\AgencyPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\ManageReferenceDataPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TenderPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Event;
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
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(Tender::class, TenderPolicy::class);

        Event::listen(ProjectCreated::class, LogDomainEvent::class);
        Event::listen(BudgetCreated::class, LogDomainEvent::class);
        Event::listen(TenderPublished::class, LogDomainEvent::class);
        Event::listen(ContractAwarded::class, LogDomainEvent::class);
        Event::listen(DocumentUploaded::class, LogDomainEvent::class);
    }
}
