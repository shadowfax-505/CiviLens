<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to correct or withdraw something published here.
 *
 * Refusals are recorded as fully as grants. A route that only keeps the
 * requests it agreed with cannot be audited, and being auditable is the whole
 * claim this project makes about itself.
 */
class CorrectionRequest extends Model
{
    use HasFactory;

    public const RECEIVED = 'received';

    public const UPHELD = 'upheld';

    public const REFUSED = 'refused';

    /** Something factually wrong about a published figure or document. */
    public const CORRECTION = 'correction';

    /** A request that something be withdrawn from publication. */
    public const WITHDRAWAL = 'withdrawal';

    protected $fillable = [
        'reference',
        'about',
        'reason',
        'kind',
        'contact',
        'status',
        'decision',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function isDecided(): bool
    {
        return $this->status !== self::RECEIVED;
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
