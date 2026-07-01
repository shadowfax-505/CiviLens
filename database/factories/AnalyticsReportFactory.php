<?php

namespace Database\Factories;

use App\Models\AnalyticsReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnalyticsReportFactory extends Factory
{
    protected $model = AnalyticsReport::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'name' => 'Executive Report',
            'dashboard' => 'executive',
            'format' => 'csv',
            'status' => 'generated',
            'filters' => [],
            'payload' => ['metrics' => []],
            'generated_at' => now(),
            'expires_at' => now()->addDays(7),
        ];
    }
}
