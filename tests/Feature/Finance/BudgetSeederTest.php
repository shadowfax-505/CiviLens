<?php

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetStatus;
use App\Models\BudgetTransactionType;
use App\Models\BudgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds budget lookup data and a baseline budget', function (): void {
    $this->seed();

    expect(BudgetCategory::query()->where('slug', 'capital-works')->exists())->toBeTrue()
        ->and(BudgetType::query()->where('slug', 'development')->exists())->toBeTrue()
        ->and(BudgetStatus::query()->where('slug', 'approved')->exists())->toBeTrue()
        ->and(BudgetTransactionType::query()->where('slug', 'expenditure')->exists())->toBeTrue()
        ->and(Budget::query()->exists())->toBeTrue();
});
