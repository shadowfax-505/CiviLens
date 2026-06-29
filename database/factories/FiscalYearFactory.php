<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2020, 2040);

        return [
            'name' => 'FY '.$year,
            'starts_on' => "{$year}-07-01",
            'ends_on' => ($year + 1).'-06-30',
            'is_active' => false,
        ];
    }
}
