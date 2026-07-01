<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Analytics\SnapshotService;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAnalyticsSnapshot implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $period,
        public string $dashboard,
        public array $filters = [],
        public ?int $userId = null,
    ) {}

    public function handle(SnapshotService $snapshots): void
    {
        $snapshots->generate(
            $this->period,
            $this->dashboard,
            AnalyticsFilters::fromArray($this->filters),
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
