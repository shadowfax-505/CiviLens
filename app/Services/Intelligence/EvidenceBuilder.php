<?php

namespace App\Services\Intelligence;

use App\Events\IntelligenceEvidenceLinked;
use App\Models\IntelligenceEvidence;
use App\Models\IntelligenceIndicator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EvidenceBuilder
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function link(IntelligenceIndicator $indicator, Model $source, array $data, ?User $user = null): IntelligenceEvidence
    {
        $evidence = IntelligenceEvidence::query()->create([
            'intelligence_indicator_id' => $indicator->id,
            'evidenceable_type' => $source::class,
            'evidenceable_id' => $source->getKey(),
            'label' => $data['label'],
            'summary' => $data['summary'] ?? null,
            'weight' => $data['weight'] ?? 50,
            'payload' => $data['payload'] ?? [],
            'created_by' => $user?->id,
        ]);

        IntelligenceEvidenceLinked::dispatch($evidence);

        return $evidence;
    }
}
