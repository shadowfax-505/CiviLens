<?php

namespace App\Models;

use Database\Factories\CompletionCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'issued_by', 'certificate_number', 'issued_at', 'status', 'notes'])]
class CompletionCertificate extends Model
{
    /** @use HasFactory<CompletionCertificateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['issued_at' => 'date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
