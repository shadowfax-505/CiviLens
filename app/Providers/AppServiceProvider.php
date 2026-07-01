<?php

namespace App\Providers;

use App\Contracts\Search\SearchProvider;
use App\Events\AlertTriggered;
use App\Events\AnalyticsCacheRefreshed;
use App\Events\AnalyticsUpdated;
use App\Events\AwardApproved;
use App\Events\BidOpened;
use App\Events\BudgetCreated;
use App\Events\CertificationExpiring;
use App\Events\CitizenReportArchived;
use App\Events\CitizenReportStatusChanged;
use App\Events\CitizenReportSubmitted;
use App\Events\ComplianceFailed;
use App\Events\ContractAwarded;
use App\Events\ContractClosed;
use App\Events\ContractorRegistered;
use App\Events\DashboardViewed;
use App\Events\DocumentArchived;
use App\Events\DocumentMetadataUpdated;
use App\Events\DocumentUpdated;
use App\Events\DocumentUploaded;
use App\Events\DocumentVersionCreated;
use App\Events\EntityIndexed;
use App\Events\EntityReindexed;
use App\Events\EvaluationCompleted;
use App\Events\InsightGenerated;
use App\Events\IntelligenceEvidenceLinked;
use App\Events\IntelligenceIndicatorDetected;
use App\Events\IntelligenceIndicatorReviewed;
use App\Events\IntelligenceProcessingJobCompleted;
use App\Events\IntelligenceProcessingJobFailed;
use App\Events\IntelligenceProcessingJobQueued;
use App\Events\IntelligenceRuleExecuted;
use App\Events\LicenseExpiring;
use App\Events\MetricCalculated;
use App\Events\MilestoneCompleted;
use App\Events\PerformanceSnapshotCreated;
use App\Events\ProcurementPlanApproved;
use App\Events\ProjectCreated;
use App\Events\PublicDocumentDownloaded;
use App\Events\PublicProjectViewed;
use App\Events\PublicSearchExecuted;
use App\Events\ReportGenerated;
use App\Events\RiskScoreUpdated;
use App\Events\SavedSearchCreated;
use App\Events\SearchExecuted;
use App\Events\SearchFailed;
use App\Events\SnapshotGenerated;
use App\Events\SuggestionGenerated;
use App\Events\TenderPublished;
use App\Events\VariationApproved;
use App\Listeners\LogDomainEvent;
use App\Models\AdministrativeUnion;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Models\IntelligenceRule;
use App\Models\Organization;
use App\Models\ProcurementPlan;
use App\Models\Project;
use App\Models\Tender;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Ward;
use App\Policies\AgencyPolicy;
use App\Policies\AnalyticsPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\CitizenReportPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\IntelligencePolicy;
use App\Policies\ManageReferenceDataPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ProcurementPlanPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TenderPolicy;
use App\Policies\UserPolicy;
use App\Services\Search\DatabaseSearchProvider;
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
        $this->app->bind(SearchProvider::class, function ($app): SearchProvider {
            return match (config('civiclens.search.provider', 'database')) {
                'database' => $app->make(DatabaseSearchProvider::class),
                default => $app->make(DatabaseSearchProvider::class),
            };
        });
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
        Gate::policy(AnalyticsReport::class, AnalyticsPolicy::class);
        Gate::policy(AnalyticsSnapshot::class, AnalyticsPolicy::class);
        Gate::policy(AnalyticsAlert::class, AnalyticsPolicy::class);
        Gate::policy(IntelligenceIndicator::class, IntelligencePolicy::class);
        Gate::policy(IntelligenceRule::class, IntelligencePolicy::class);
        Gate::policy(IntelligenceProcessingJob::class, IntelligencePolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(Tender::class, TenderPolicy::class);
        Gate::policy(CitizenReport::class, CitizenReportPolicy::class);
        Gate::policy(ProcurementPlan::class, ProcurementPlanPolicy::class);

        Event::listen(ProjectCreated::class, LogDomainEvent::class);
        Event::listen(BudgetCreated::class, LogDomainEvent::class);
        Event::listen(TenderPublished::class, LogDomainEvent::class);
        Event::listen(ContractAwarded::class, LogDomainEvent::class);
        Event::listen(ProcurementPlanApproved::class, LogDomainEvent::class);
        Event::listen(BidOpened::class, LogDomainEvent::class);
        Event::listen(EvaluationCompleted::class, LogDomainEvent::class);
        Event::listen(AwardApproved::class, LogDomainEvent::class);
        Event::listen(MilestoneCompleted::class, LogDomainEvent::class);
        Event::listen(VariationApproved::class, LogDomainEvent::class);
        Event::listen(ContractClosed::class, LogDomainEvent::class);
        Event::listen(DocumentUploaded::class, LogDomainEvent::class);
        Event::listen(DocumentUpdated::class, LogDomainEvent::class);
        Event::listen(DocumentArchived::class, LogDomainEvent::class);
        Event::listen(DocumentVersionCreated::class, LogDomainEvent::class);
        Event::listen(DocumentMetadataUpdated::class, LogDomainEvent::class);
        Event::listen(ContractorRegistered::class, LogDomainEvent::class);
        Event::listen(LicenseExpiring::class, LogDomainEvent::class);
        Event::listen(CertificationExpiring::class, LogDomainEvent::class);
        Event::listen(ComplianceFailed::class, LogDomainEvent::class);
        Event::listen(RiskScoreUpdated::class, LogDomainEvent::class);
        Event::listen(PerformanceSnapshotCreated::class, LogDomainEvent::class);
        Event::listen(EntityIndexed::class, LogDomainEvent::class);
        Event::listen(EntityReindexed::class, LogDomainEvent::class);
        Event::listen(SearchExecuted::class, LogDomainEvent::class);
        Event::listen(SearchFailed::class, LogDomainEvent::class);
        Event::listen(SuggestionGenerated::class, LogDomainEvent::class);
        Event::listen(SavedSearchCreated::class, LogDomainEvent::class);
        Event::listen(AnalyticsUpdated::class, LogDomainEvent::class);
        Event::listen(MetricCalculated::class, LogDomainEvent::class);
        Event::listen(DashboardViewed::class, LogDomainEvent::class);
        Event::listen(SnapshotGenerated::class, LogDomainEvent::class);
        Event::listen(ReportGenerated::class, LogDomainEvent::class);
        Event::listen(AlertTriggered::class, LogDomainEvent::class);
        Event::listen(InsightGenerated::class, LogDomainEvent::class);
        Event::listen(AnalyticsCacheRefreshed::class, LogDomainEvent::class);
        Event::listen(IntelligenceRuleExecuted::class, LogDomainEvent::class);
        Event::listen(IntelligenceIndicatorDetected::class, LogDomainEvent::class);
        Event::listen(IntelligenceIndicatorReviewed::class, LogDomainEvent::class);
        Event::listen(IntelligenceEvidenceLinked::class, LogDomainEvent::class);
        Event::listen(IntelligenceProcessingJobQueued::class, LogDomainEvent::class);
        Event::listen(IntelligenceProcessingJobCompleted::class, LogDomainEvent::class);
        Event::listen(IntelligenceProcessingJobFailed::class, LogDomainEvent::class);
        Event::listen(CitizenReportSubmitted::class, LogDomainEvent::class);
        Event::listen(CitizenReportStatusChanged::class, LogDomainEvent::class);
        Event::listen(CitizenReportArchived::class, LogDomainEvent::class);
        Event::listen(PublicSearchExecuted::class, LogDomainEvent::class);
        Event::listen(PublicDocumentDownloaded::class, LogDomainEvent::class);
        Event::listen(PublicProjectViewed::class, LogDomainEvent::class);
    }
}
