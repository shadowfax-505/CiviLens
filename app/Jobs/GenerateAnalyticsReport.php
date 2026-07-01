<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Analytics\ReportBuilder;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAnalyticsReport implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $dashboard,
        public string $format,
        public array $filters = [],
        public ?int $userId = null,
    ) {}

    public function handle(ReportBuilder $reports): void
    {
        $reports->generate(
            $this->dashboard,
            $this->format,
            AnalyticsFilters::fromArray($this->filters),
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
