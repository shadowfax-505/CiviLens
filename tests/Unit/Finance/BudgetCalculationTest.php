<?php

use App\Models\Budget;
use App\Models\BudgetTransaction;
use App\Models\BudgetTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates remaining balance utilization and preserves transaction relationships', function (): void {
    $budget = Budget::factory()->create([
        'current_allocation' => 1000000,
        'reserved_amount' => 100000,
        'committed_amount' => 200000,
        'actual_expenditure' => 250000,
    ]);
    $type = BudgetTransactionType::factory()->create(['slug' => 'expenditure', 'direction' => 'decrease']);
    $transaction = BudgetTransaction::factory()->for($budget)->for($type, 'type')->create(['amount' => 250000]);

    expect((float) $budget->remaining_balance)->toBe(450000.0)
        ->and((float) $budget->utilization_percentage)->toBe(25.0)
        ->and($budget->transactions()->whereKey($transaction)->exists())->toBeTrue()
        ->and($transaction->budget->is($budget))->toBeTrue();
});
