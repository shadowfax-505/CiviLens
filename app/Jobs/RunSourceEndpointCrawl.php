<?php

namespace App\Jobs;

use App\Models\SourceEndpoint;
use App\Models\User;
use App\Services\Ingestion\SourceAcquisitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSourceEndpointCrawl implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $endpointId,
        public readonly ?int $actorId = null,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(SourceAcquisitionService $acquisition): void
    {
        $endpoint = SourceEndpoint::query()->findOrFail($this->endpointId);
        $actor = $this->actorId === null ? null : User::query()->find($this->actorId);
        $acquisition->discover($endpoint, $actor instanceof User ? $actor : null);
    }

    public function uniqueId(): string
    {
        return 'source-endpoint:'.$this->endpointId;
    }
}
