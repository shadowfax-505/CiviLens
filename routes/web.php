<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\Analytics\AnalyticsAlertController;
use App\Http\Controllers\Admin\Analytics\AnalyticsDashboardController;
use App\Http\Controllers\Admin\Analytics\AnalyticsMetricController;
use App\Http\Controllers\Admin\Analytics\AnalyticsReportController;
use App\Http\Controllers\Admin\Analytics\AnalyticsSnapshotController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\CitizenReports\CitizenReportModerationController;
use App\Http\Controllers\Admin\Contractors\ContractorProfileController;
use App\Http\Controllers\Admin\Contractors\OrganizationController;
use App\Http\Controllers\Admin\Documents\DocumentBulkActionController;
use App\Http\Controllers\Admin\Documents\DocumentController;
use App\Http\Controllers\Admin\Documents\DocumentVersionController;
use App\Http\Controllers\Admin\ExecutiveDashboardController;
use App\Http\Controllers\Admin\Finance\BudgetController;
use App\Http\Controllers\Admin\Finance\BudgetRevisionController;
use App\Http\Controllers\Admin\Finance\BudgetTransactionController;
use App\Http\Controllers\Admin\Geography\CountryController;
use App\Http\Controllers\Admin\Geography\DistrictController;
use App\Http\Controllers\Admin\Geography\DivisionController;
use App\Http\Controllers\Admin\Geography\ProjectMapController;
use App\Http\Controllers\Admin\Geography\UnionController;
use App\Http\Controllers\Admin\Geography\UpazilaController;
use App\Http\Controllers\Admin\Geography\WardController;
use App\Http\Controllers\Admin\Intelligence\IntelligenceDashboardController;
use App\Http\Controllers\Admin\Intelligence\IntelligenceIndicatorController;
use App\Http\Controllers\Admin\Intelligence\IntelligenceProcessingJobController;
use App\Http\Controllers\Admin\Intelligence\IntelligenceRuleController;
use App\Http\Controllers\Admin\Procurement\AwardController;
use App\Http\Controllers\Admin\Procurement\BidSubmissionController;
use App\Http\Controllers\Admin\Procurement\ContractController;
use App\Http\Controllers\Admin\Procurement\EvaluationController;
use App\Http\Controllers\Admin\Procurement\ProcurementPlanController;
use App\Http\Controllers\Admin\Procurement\ProcurementWorkflowController;
use App\Http\Controllers\Admin\Procurement\TenderController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectMapDataController;
use App\Http\Controllers\Admin\SearchAnalyticsController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SearchKnowledgeController;
use App\Http\Controllers\Admin\Sources\SourceFindingsController;
use App\Http\Controllers\Admin\Sources\SourceRegistryController;
use App\Http\Controllers\Admin\SystemMetricsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Citizen\CitizenReportDashboardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPortal\CitizenReportController;
use App\Http\Controllers\PublicPortal\PublicAgencyController;
use App\Http\Controllers\PublicPortal\PublicContractorController;
use App\Http\Controllers\PublicPortal\PublicDistrictLocatorController;
use App\Http\Controllers\PublicPortal\PublicDocumentController;
use App\Http\Controllers\PublicPortal\PublicHomeController;
use App\Http\Controllers\PublicPortal\PublicProcurementController;
use App\Http\Controllers\PublicPortal\PublicProjectController;
use App\Http\Controllers\PublicPortal\PublicProjectMapDataController;
use App\Http\Controllers\PublicPortal\PublicSearchController;
use App\Http\Controllers\VersionController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('public.home');

Route::get('/healthz', HealthCheckController::class)->name('healthz');
Route::get('/version', VersionController::class)->name('version');
Route::get('/about', PublicHomeController::class)->name('public.about');

Route::prefix('public')->name('public.')->group(function (): void {
    Route::get('/', PublicHomeController::class)->name('legacy-home');
    Route::post('/district/resolve', PublicDistrictLocatorController::class)
        ->middleware('throttle:30,1')
        ->name('district.resolve');
    Route::get('/projects', [PublicProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/map-data', PublicProjectMapDataController::class)->middleware('throttle:60,1')->name('projects.map-data');
    Route::get('/projects/{project:slug}', [PublicProjectController::class, 'show'])->name('projects.show');
    Route::get('/agencies', [PublicAgencyController::class, 'index'])->name('agencies.index');
    Route::get('/agencies/{agency:slug}', [PublicAgencyController::class, 'show'])->name('agencies.show');
    Route::get('/procurement', PublicProcurementController::class)->name('procurement.index');
    Route::get('/procurement/{tender:slug}', [PublicProcurementController::class, 'show'])->name('procurement.show');
    Route::get('/contractors', [PublicContractorController::class, 'index'])->name('contractors.index');
    Route::get('/contractors/{organization}', [PublicContractorController::class, 'show'])->name('contractors.show');
    Route::get('/documents', [PublicDocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/download', [PublicDocumentController::class, 'download'])->name('documents.download');
    Route::get('/search', PublicSearchController::class)->middleware('throttle:60,1')->name('search');
    Route::get('/reports/{uuid}', [CitizenReportController::class, 'show'])->whereUuid('uuid')->name('reports.show');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::post('/verify-email/otp', [EmailVerificationController::class, 'verifyOtp'])
        ->middleware('throttle:5,10')
        ->name('verification.otp');

    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::get('/dashboard', ExecutiveDashboardController::class)->name('dashboard');

    Route::get('/settings', [ProfileController::class, 'show'])->name('settings.show');
    Route::match(['GET', 'HEAD'], '/profile', fn () => redirect('/settings', 302))->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::put('/profile/districts', [ProfileController::class, 'updateDistricts'])->name('profile.districts');

    Route::get('/public/reports/create', [CitizenReportController::class, 'create'])->name('public.reports.create');
    Route::post('/public/reports', [CitizenReportController::class, 'store'])
        ->middleware('throttle:10,10')
        ->name('public.reports.store');

    Route::prefix('citizen')->name('citizen.')->group(function (): void {
        Route::get('/reports', [CitizenReportDashboardController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [CitizenReportDashboardController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/attachment', [CitizenReportController::class, 'downloadAttachment'])->name('reports.attachment');
    });

    Route::prefix('admin')->name('admin.')->middleware('admin.access')->group(function (): void {
        Route::get('/system/metrics', SystemMetricsController::class)->name('system.metrics');

        Route::prefix('sources')->name('sources.')->group(function (): void {
            Route::get('/', [SourceRegistryController::class, 'index'])->name('index');
            Route::get('/findings', SourceFindingsController::class)->name('findings');
            Route::post('/publishers', [SourceRegistryController::class, 'storePublisher'])->name('publishers.store');
            Route::post('/endpoints', [SourceRegistryController::class, 'storeEndpoint'])->name('endpoints.store');
            Route::patch('/endpoints/{endpoint}/pause', [SourceRegistryController::class, 'pause'])->name('endpoints.pause');
            Route::patch('/endpoints/{endpoint}/resume', [SourceRegistryController::class, 'resume'])->name('endpoints.resume');
            Route::post('/endpoints/{endpoint}/run', [SourceRegistryController::class, 'run'])->name('endpoints.run');
        });

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
        Route::patch('/users/{user}/lock', [UserController::class, 'updateLock'])->name('users.lock');
        Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles');
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

        Route::prefix('citizen-reports')->name('citizen-reports.')->group(function (): void {
            Route::get('/', [CitizenReportModerationController::class, 'index'])->name('index');
            Route::get('/{report}', [CitizenReportModerationController::class, 'show'])->name('show');
            Route::patch('/{report}/status', [CitizenReportModerationController::class, 'status'])->name('status');
            Route::patch('/{report}/archive', [CitizenReportModerationController::class, 'archive'])->name('archive');
            Route::patch('/{report}/restore', [CitizenReportModerationController::class, 'restore'])->name('restore');
            Route::post('/{report}/acknowledgement', [CitizenReportModerationController::class, 'resendAcknowledgement'])->name('acknowledgement.resend');
        });

        Route::prefix('change-requests')->name('change-requests.')->group(function (): void {
            Route::get('/', [ChangeRequestController::class, 'index'])->name('index');
            Route::get('/create', [ChangeRequestController::class, 'create'])->name('create');
            Route::post('/', [ChangeRequestController::class, 'store'])->name('store');
            Route::get('/{changeRequest}', [ChangeRequestController::class, 'show'])->name('show');
            Route::get('/{changeRequest}/attachment', [ChangeRequestController::class, 'downloadAttachment'])->name('attachment.download');
            Route::patch('/{changeRequest}', [ChangeRequestController::class, 'update'])->name('update');
            Route::post('/{changeRequest}/applied', [ChangeRequestController::class, 'markApplied'])->name('applied');
        });

        Route::resource('agencies', AgencyController::class)->except('show');

        Route::prefix('intelligence')->name('intelligence.')->group(function (): void {
            Route::get('/', IntelligenceDashboardController::class)->name('index');
            Route::get('/dashboard/summary', [IntelligenceDashboardController::class, 'summary'])->name('dashboard.summary');
            Route::post('/engine/run', [IntelligenceDashboardController::class, 'runEngine'])->name('engine.run');
            Route::get('/indicators', [IntelligenceIndicatorController::class, 'index'])->name('indicators.index');
            Route::get('/indicators/{indicator}', [IntelligenceIndicatorController::class, 'show'])->name('indicators.show');
            Route::patch('/indicators/{indicator}/review', [IntelligenceIndicatorController::class, 'review'])->name('indicators.review');
            Route::get('/rules', [IntelligenceRuleController::class, 'index'])->name('rules.index');
            Route::get('/rules/{rule}', [IntelligenceRuleController::class, 'show'])->name('rules.show');
            Route::patch('/rules/{rule}', [IntelligenceRuleController::class, 'update'])->name('rules.update');
            Route::post('/rules/{rule}/run', [IntelligenceRuleController::class, 'run'])->name('rules.run');
            Route::get('/rules/{rule}/preview', [IntelligenceRuleController::class, 'preview'])->name('rules.preview');
            Route::post('/rules/{rule}/dry-run', [IntelligenceRuleController::class, 'dryRun'])->name('rules.dry-run');
            Route::get('/processing-jobs', [IntelligenceProcessingJobController::class, 'index'])->name('processing-jobs.index');
            Route::post('/processing-jobs', [IntelligenceProcessingJobController::class, 'store'])->name('processing-jobs.store');
        });

        Route::prefix('analytics')->name('analytics.')->group(function (): void {
            Route::get('/', AnalyticsDashboardController::class)->name('index');
            Route::get('/metrics', AnalyticsMetricController::class)->name('metrics');
            Route::post('/snapshots', [AnalyticsSnapshotController::class, 'store'])->name('snapshots.store');
            Route::post('/snapshots/realtime', [AnalyticsSnapshotController::class, 'realtime'])->name('snapshots.realtime');
            Route::get('/reports', [AnalyticsReportController::class, 'index'])->name('reports.index');
            Route::post('/reports', [AnalyticsReportController::class, 'store'])->name('reports.store');
            Route::post('/reports/csv', [AnalyticsReportController::class, 'csv'])->name('reports.csv');
            Route::get('/reports/{report}/download', [AnalyticsReportController::class, 'download'])->name('reports.download');
            Route::get('/alerts', AnalyticsAlertController::class)->name('alerts');
        });

        Route::prefix('search')->name('search.')->group(function (): void {
            Route::get('/', [SearchController::class, 'index'])->name('index');
            Route::get('/advanced', [SearchController::class, 'advanced'])->name('advanced');
            Route::get('/analytics', SearchAnalyticsController::class)->name('analytics');
            Route::get('/results', [SearchController::class, 'results'])->name('results');
            Route::get('/suggestions', [SearchController::class, 'suggestions'])->name('suggestions');
            Route::post('/saved', [SearchController::class, 'save'])->name('saved');
            Route::post('/clicks', [SearchController::class, 'click'])->name('clicks');
            Route::get('/knowledge/{module}/{id}', SearchKnowledgeController::class)->name('knowledge');
        });

        Route::prefix('documents')->name('documents.')->group(function (): void {
            Route::get('/archived', [DocumentController::class, 'archived'])->name('archived');
            Route::post('/bulk', [DocumentBulkActionController::class, 'store'])->name('bulk');
            Route::post('/{document}/versions', [DocumentVersionController::class, 'store'])->name('versions.store');
            Route::get('/{document}/versions/{version}/download', [DocumentVersionController::class, 'download'])->name('versions.download');
            Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
            Route::get('/{document}/preview', [DocumentController::class, 'preview'])->name('preview');
            Route::patch('/{document}/archive', [DocumentController::class, 'archive'])->name('archive');
            Route::patch('/{document}/restore', [DocumentController::class, 'restore'])->name('restore');
        });
        Route::resource('documents', DocumentController::class)->except('destroy');

        Route::prefix('contractors')->name('contractors.')->group(function (): void {
            Route::get('/organizations/archived', [OrganizationController::class, 'archived'])->name('organizations.archived');
            Route::patch('/organizations/{organization}/archive', [OrganizationController::class, 'archive'])->name('organizations.archive');
            Route::patch('/organizations/{organization}/restore', [OrganizationController::class, 'restore'])->name('organizations.restore');
            Route::post('/organizations/{organization}/profile', [ContractorProfileController::class, 'store'])->name('organizations.profile.store');
            Route::resource('organizations', OrganizationController::class)->except('destroy');
        });

        Route::get('/projects/archived', [ProjectController::class, 'archived'])->name('projects.archived');
        Route::get('/projects/map', [ProjectMapController::class, 'index'])->name('projects.map');
        Route::get('/projects/map-data', ProjectMapDataController::class)->middleware('throttle:120,1')->name('projects.map-data');
        Route::patch('/projects/map/{project}', [ProjectMapController::class, 'update'])->name('projects.map.update');
        Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::patch('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
        Route::resource('projects', ProjectController::class);

        Route::prefix('finance')->name('finance.')->group(function (): void {
            Route::get('/budgets/archived', [BudgetController::class, 'archived'])->name('budgets.archived');
            Route::patch('/budgets/{budget}/archive', [BudgetController::class, 'archive'])->name('budgets.archive');
            Route::patch('/budgets/{budget}/restore', [BudgetController::class, 'restore'])->name('budgets.restore');
            Route::post('/budgets/{budget}/revisions', [BudgetRevisionController::class, 'store'])->name('budgets.revisions.store');
            Route::post('/budgets/{budget}/transactions', [BudgetTransactionController::class, 'store'])->name('budgets.transactions.store');
            Route::delete('/budget-transactions/{budgetTransaction}', [BudgetTransactionController::class, 'destroy'])->name('budget-transactions.destroy');
            Route::resource('budgets', BudgetController::class)->except('destroy');
        });

        Route::prefix('procurement')->name('procurement.')->group(function (): void {
            Route::get('/plans', [ProcurementPlanController::class, 'index'])->name('plans.index');
            Route::get('/plans/create', [ProcurementPlanController::class, 'create'])->name('plans.create');
            Route::post('/plans', [ProcurementPlanController::class, 'store'])->name('plans.store');
            Route::get('/plans/{plan}', [ProcurementPlanController::class, 'show'])->name('plans.show');
            Route::patch('/plans/{plan}/approve', [ProcurementPlanController::class, 'approve'])->name('plans.approve');
            Route::get('/tenders/archived', [TenderController::class, 'archived'])->name('tenders.archived');
            Route::patch('/tenders/{tender}/publish', [TenderController::class, 'publish'])->name('tenders.publish');
            Route::patch('/tenders/{tender}/close', [TenderController::class, 'close'])->name('tenders.close');
            Route::patch('/tenders/{tender}/archive', [TenderController::class, 'archive'])->name('tenders.archive');
            Route::patch('/tenders/{tender}/restore', [TenderController::class, 'restore'])->name('tenders.restore');
            Route::post('/tenders/{tender}/bids', [BidSubmissionController::class, 'store'])->name('tenders.bids.store');
            Route::post('/tenders/{tender}/criteria', [EvaluationController::class, 'storeCriterion'])->name('tenders.criteria.store');
            Route::post('/bid-submissions/{bidSubmission}/scores', [EvaluationController::class, 'storeScore'])->name('bid-submissions.scores.store');
            Route::post('/bid-submissions/{bidSubmission}/open', [ProcurementWorkflowController::class, 'openBid'])->name('bid-submissions.open');
            Route::post('/bid-submissions/{bidSubmission}/finalize-evaluation', [ProcurementWorkflowController::class, 'finalizeEvaluation'])->name('bid-submissions.evaluations.finalize');
            Route::post('/tenders/{tender}/awards', [AwardController::class, 'store'])->name('tenders.awards.store');
            Route::patch('/awards/{award}/approve', [ProcurementWorkflowController::class, 'approveAward'])->name('awards.approve');
            Route::post('/awards/{award}/contracts', [ContractController::class, 'store'])->name('awards.contracts.store');
            Route::post('/contracts/{contract}/payments', [ProcurementWorkflowController::class, 'recordPayment'])->name('contracts.payments.store');
            Route::patch('/contracts/{contract}/close', [ProcurementWorkflowController::class, 'closeContract'])->name('contracts.close');
            Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
            Route::patch('/milestones/{milestone}/complete', [ProcurementWorkflowController::class, 'completeMilestone'])->name('milestones.complete');
            Route::patch('/variation-orders/{variationOrder}/approve', [ProcurementWorkflowController::class, 'approveVariation'])->name('variation-orders.approve');
            Route::resource('tenders', TenderController::class)->except('destroy');
        });

        Route::prefix('geography')->name('geography.')->group(function (): void {
            Route::get('/project-map', function (Request $request) {
                abort_unless($request->user()?->can('viewAny', Project::class) === true, 403);

                return redirect()->route('admin.projects.map');
            })->name('project-map.index');
            Route::patch('/project-map/{project}', fn (Project $project) => redirect()->route('admin.projects.map', ['project_id' => $project->id], 307))->name('project-map.update');
            Route::resource('countries', CountryController::class)->except('show');
            Route::resource('divisions', DivisionController::class)->except('show');
            Route::resource('districts', DistrictController::class)->except('show');
            Route::resource('upazilas', UpazilaController::class)->except('show');
            Route::resource('unions', UnionController::class)->except('show')->parameters(['unions' => 'union']);
            Route::resource('wards', WardController::class)->except('show');
        });
    });
});
