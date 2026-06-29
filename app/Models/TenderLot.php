<?php

namespace App\Models;

use Database\Factories\TenderLotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tender_id', 'lot_number', 'title', 'description', 'sort_order'])]
class TenderLot extends Model
{
    /** @use HasFactory<TenderLotFactory> */
    use HasFactory;

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
