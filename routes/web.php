<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\Finance\BudgetController;
use App\Http\Controllers\Admin\Finance\BudgetRevisionController;
use App\Http\Controllers\Admin\Finance\BudgetTransactionController;
use App\Http\Controllers\Admin\Geography\CountryController;
use App\Http\Controllers\Admin\Geography\DistrictController;
use App\Http\Controllers\Admin\Geography\DivisionController;
use App\Http\Controllers\Admin\Geography\UnionController;
use App\Http\Controllers\Admin\Geography\UpazilaController;
use App\Http\Controllers\Admin\Geography\WardController;
use App\Http\Controllers\Admin\Procurement\AwardController;
use App\Http\Controllers\Admin\Procurement\BidSubmissionController;
use App\Http\Controllers\Admin\Procurement\ContractController;
use App\Http\Controllers\Admin\Procurement\EvaluationController;
use App\Http\Controllers\Admin\Procurement\TenderController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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

    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
        Route::patch('/users/{user}/lock', [UserController::class, 'updateLock'])->name('users.lock');
        Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles');
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

        Route::resource('agencies', AgencyController::class)->except('show');
        Route::get('/projects/archived', [ProjectController::class, 'archived'])->name('projects.archived');
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
            Route::get('/tenders/archived', [TenderController::class, 'archived'])->name('tenders.archived');
            Route::patch('/tenders/{tender}/publish', [TenderController::class, 'publish'])->name('tenders.publish');
            Route::patch('/tenders/{tender}/close', [TenderController::class, 'close'])->name('tenders.close');
            Route::patch('/tenders/{tender}/archive', [TenderController::class, 'archive'])->name('tenders.archive');
            Route::patch('/tenders/{tender}/restore', [TenderController::class, 'restore'])->name('tenders.restore');
            Route::post('/tenders/{tender}/bids', [BidSubmissionController::class, 'store'])->name('tenders.bids.store');
            Route::post('/tenders/{tender}/criteria', [EvaluationController::class, 'storeCriterion'])->name('tenders.criteria.store');
            Route::post('/bid-submissions/{bidSubmission}/scores', [EvaluationController::class, 'storeScore'])->name('bid-submissions.scores.store');
            Route::post('/tenders/{tender}/awards', [AwardController::class, 'store'])->name('tenders.awards.store');
            Route::post('/awards/{award}/contracts', [ContractController::class, 'store'])->name('awards.contracts.store');
            Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
            Route::resource('tenders', TenderController::class)->except('destroy');
        });

        Route::prefix('geography')->name('geography.')->group(function (): void {
            Route::resource('countries', CountryController::class)->except('show');
            Route::resource('divisions', DivisionController::class)->except('show');
            Route::resource('districts', DistrictController::class)->except('show');
            Route::resource('upazilas', UpazilaController::class)->except('show');
            Route::resource('unions', UnionController::class)->except('show')->parameters(['unions' => 'union']);
            Route::resource('wards', WardController::class)->except('show');
        });
    });
});
