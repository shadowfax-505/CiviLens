<?php

namespace App\Models;

use Database\Factories\BidDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_submission_id', 'uploaded_by', 'title', 'document_type', 'file_path', 'uploaded_at'])]
class BidDocument extends Model
{
    /** @use HasFactory<BidDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
