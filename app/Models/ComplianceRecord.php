<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'next_review_date' => 'date',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ContractorProfile::class, 'contractor_profile_id');
    }

    public function contractorProfile(): BelongsTo
    {
        return $this->belongsTo(ContractorProfile::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ComplianceType::class, 'compliance_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ComplianceStatus::class, 'compliance_status_id');
    }
}
