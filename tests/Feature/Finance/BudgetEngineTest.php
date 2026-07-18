<?php

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetRevision;
use App\Models\BudgetStatus;
use App\Models\BudgetTransaction;
use App\Models\BudgetTransactionType;
use App\Models\BudgetType;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function financeAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function budgetPayload(array $overrides = []): array
{
    $project = Project::factory()->create();
    $fiscalYear = FiscalYear::query()->firstOrCreate(['name' => 'FY 2099'], ['starts_on' => '2098-07-01', 'ends_on' => '2099-06-30']);
    $fundingSource = FundingSource::query()->firstOrCreate(['slug' => 'public-funds'], ['name' => 'Public Funds']);
    $category = BudgetCategory::query()->firstOrCreate(['slug' => 'capital-works'], ['name' => 'Capital Works']);
    $type = BudgetType::query()->firstOrCreate(['slug' => 'development'], ['name' => 'Development']);
    $status = BudgetStatus::query()->firstOrCreate(['slug' => 'approved'], ['name' => 'Approved']);

    return array_merge([
        'project_id' => $project->id,
        'fiscal_year_id' => $fiscalYear->id,
        'funding_source_id' => $fundingSource->id,
        'budget_category_id' => $category->id,
        'budget_type_id' => $type->id,
        'budget_status_id' => $status->id,
        'original_allocation' => '1000000.00',
        'current_allocation' => '1000000.00',
        'reserved_amount' => '50000.00',
        'committed_amount' => '200000.00',
        'actual_expenditure' => '125000.00',
        'currency' => 'BDT',
        'notes' => 'Initial approved allocation.',
        'is_active' => '1',
    ], $overrides);
}

function financeStaff(): User
{
    $role = Role::query()->create(['name' => 'Government Staff', 'slug' => config('civiclens.roles.staff')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function budgetCitizen(): User
{
    $role = Role::query()->create(['name' => 'Citizen', 'slug' => config('civiclens.roles.citizen')]);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->attach($role);

    return $user;
}

it('lets staff view the budget dashboard but blocks them from directly creating updating or archiving budgets', function (): void {
    $admin = financeAdmin();
    $staff = financeStaff();
    $payload = budgetPayload();

    $this->actingAs($admin)->post('/admin/finance/budgets', $payload)->assertRedirect();
    $budget = Budget::query()->where('project_id', $payload['project_id'])->firstOrFail();

    $this->actingAs($staff)->get('/admin/finance/budgets')->assertOk();
    $this->actingAs($staff)->get('/admin/finance/budgets/create')->assertForbidden();
    $this->actingAs($staff)->post('/admin/finance/budgets', budgetPayload())->assertForbidden();
    $this->actingAs($staff)->get("/admin/finance/budgets/{$budget->id}/edit")->assertForbidden();
    $this->actingAs($staff)->patch("/admin/finance/budgets/{$budget->id}/archive")->assertForbidden();
});

it('hides the modify budgets button from staff while giving admins a working create link', function (): void {
    $admin = financeAdmin();
    $staff = financeStaff();

    $this->actingAs($staff)->get('/admin/finance/budgets')
        ->assertOk()
        ->assertDontSee('New budget')
        ->assertSee('Propose budget');

    $this->actingAs($admin)->get('/admin/finance/budgets')
        ->assertOk()
        ->assertSee('New budget');
});

it('shows citizens only budgets for public active projects with no edit or proposal controls', function (): void {
    $citizen = budgetCitizen();
    $staff = financeStaff();

    $publicProject = Project::factory()->create(['name' => 'Public Culvert Project', 'is_public' => true, 'is_active' => true]);
    $internalProject = Project::factory()->create(['name' => 'Internal Planning Project', 'is_public' => false, 'is_active' => true]);

    Budget::factory()->create(['project_id' => $publicProject->id, 'current_allocation' => 500000, 'actual_expenditure' => 100000]);
    Budget::factory()->create(['project_id' => $internalProject->id, 'current_allocation' => 900000, 'actual_expenditure' => 400000]);

    $this->actingAs($citizen)->get(route('citizen.budgets.index'))
        ->assertOk()
        ->assertSee('Public Culvert Project')
        ->assertDontSee('Internal Planning Project')
        ->assertDontSee('Propose budget')
        ->assertDontSee('Request change');

    $this->actingAs($staff)->get(route('citizen.budgets.index'))->assertForbidden();
});

it('restricts financial management to authorized administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/finance/budgets')->assertForbidden();
    $this->actingAs($user)->post('/admin/finance/budgets', [])->assertForbidden();
});

it('lets administrators create view update archive and restore budgets with calculated balances', function (): void {
    $admin = financeAdmin();
    $payload = budgetPayload();

    $this->actingAs($admin)->post('/admin/finance/budgets', $payload)->assertRedirect();

    $budget = Budget::query()->where('project_id', $payload['project_id'])->firstOrFail();

    expect((float) $budget->remaining_balance)->toBe(625000.0)
        ->and((float) $budget->utilization_percentage)->toBe(12.5);

    $this->actingAs($admin)->get("/admin/finance/budgets/{$budget->id}")
        ->assertOk()
        ->assertSee('Initial approved allocation')
        ->assertSee('625,000.00');

    $this->actingAs($admin)->put("/admin/finance/budgets/{$budget->id}", array_merge($payload, [
        'current_allocation' => '1200000.00',
        'actual_expenditure' => '225000.00',
    ]))->assertRedirect();

    expect((float) $budget->fresh()->remaining_balance)->toBe(725000.0);

    $this->actingAs($admin)->patch("/admin/finance/budgets/{$budget->id}/archive")->assertRedirect();
    expect($budget->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/finance/budgets/{$budget->id}/restore")->assertRedirect();
    expect($budget->fresh()->archived_at)->toBeNull();
});

it('tracks revisions and transactions without deleting financial history', function (): void {
    $admin = financeAdmin();
    $budget = Budget::factory()->create([
        'current_allocation' => 1000000,
        'reserved_amount' => 0,
        'committed_amount' => 0,
        'actual_expenditure' => 0,
    ]);
    $expenditureType = BudgetTransactionType::factory()->create(['name' => 'Expenditure', 'slug' => 'expenditure', 'direction' => 'decrease']);

    $this->actingAs($admin)->post("/admin/finance/budgets/{$budget->id}/revisions", [
        'new_allocation' => '1250000.00',
        'reason' => 'Scope increase approved.',
        'approval_date' => '2026-06-29',
        'approved_by' => $admin->id,
    ])->assertRedirect();

    expect(BudgetRevision::query()->where('budget_id', $budget->id)->where('revision_number', 1)->exists())->toBeTrue()
        ->and((float) $budget->fresh()->current_allocation)->toBe(1250000.0);

    $this->actingAs($admin)->post("/admin/finance/budgets/{$budget->id}/transactions", [
        'budget_transaction_type_id' => $expenditureType->id,
        'amount' => '250000.00',
        'transaction_date' => '2026-06-30',
        'description' => 'Contractor payment.',
    ])->assertRedirect();

    $transaction = BudgetTransaction::query()->where('budget_id', $budget->id)->firstOrFail();

    expect((float) $budget->fresh()->actual_expenditure)->toBe(250000.0)
        ->and((float) $budget->fresh()->remaining_balance)->toBe(1000000.0);

    $this->actingAs($admin)->delete("/admin/finance/budget-transactions/{$transaction->id}")
        ->assertStatus(405);

    expect(BudgetTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});

it('supports financial search filters sorting and pagination', function (): void {
    $admin = financeAdmin();
    $payload = budgetPayload();
    Budget::factory()->create([
        'project_id' => $payload['project_id'],
        'fiscal_year_id' => $payload['fiscal_year_id'],
        'funding_source_id' => $payload['funding_source_id'],
        'budget_category_id' => $payload['budget_category_id'],
        'budget_type_id' => $payload['budget_type_id'],
        'budget_status_id' => $payload['budget_status_id'],
        'current_allocation' => 750000,
        'actual_expenditure' => 200000,
        'currency' => 'BDT',
        'notes' => 'Bridge allocation.',
    ]);
    Budget::factory()->create(['current_allocation' => 5000000, 'notes' => 'Large water project.']);

    $query = http_build_query([
        'search' => 'Bridge',
        'project_id' => $payload['project_id'],
        'fiscal_year_id' => $payload['fiscal_year_id'],
        'funding_source_id' => $payload['funding_source_id'],
        'budget_type_id' => $payload['budget_type_id'],
        'budget_status_id' => $payload['budget_status_id'],
        'amount_min' => 100000,
        'amount_max' => 1000000,
        'sort' => 'current_allocation',
        'direction' => 'asc',
    ]);

    $this->actingAs($admin)->get('/admin/finance/budgets?'.$query)
        ->assertOk()
        ->assertSee('Bridge allocation')
        ->assertDontSee('Large water project');
});

it('validates budget relationships and financial amounts', function (): void {
    $admin = financeAdmin();

    $this->actingAs($admin)->post('/admin/finance/budgets', [
        'project_id' => 999,
        'fiscal_year_id' => 999,
        'funding_source_id' => 999,
        'budget_category_id' => 999,
        'budget_type_id' => 999,
        'budget_status_id' => 999,
        'original_allocation' => '-1',
        'current_allocation' => '-1',
        'currency' => 'BDTK',
    ])->assertSessionHasErrors([
        'project_id',
        'fiscal_year_id',
        'funding_source_id',
        'budget_category_id',
        'budget_type_id',
        'budget_status_id',
        'original_allocation',
        'current_allocation',
        'currency',
    ]);
});
