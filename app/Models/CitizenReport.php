<?php

namespace App\Models;

use Database\Factories\CitizenReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_uuid',
    'citizen_report_category_id',
    'citizen_report_status_id',
    'submitter_id',
    'assigned_to',
    'project_id',
    'agency_id',
    'document_id',
    'country_id',
    'division_id',
    'district_id',
    'upazila_id',
    'union_id',
    'ward_id',
    'title',
    'description',
    'location_text',
    'contact_preference',
    'moderation_notes',
    'submitted_at',
    'resolved_at',
    'archived_at',
])]
class CitizenReport extends Model
{
    /** @use HasFactory<CitizenReportFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CitizenReportCategory::class, 'citizen_report_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CitizenReportStatus::class, 'citizen_report_status_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function upazila(): BelongsTo
    {
        return $this->belongsTo(Upazila::class);
    }

    public function union(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnion::class, 'union_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CitizenReportActivity::class)->latest();
    }
}
