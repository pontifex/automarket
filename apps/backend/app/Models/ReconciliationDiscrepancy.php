<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReconciliationDiscrepancy extends Model
{
    use HasUuids;

    protected $table = 'reconciliation_discrepancies';

    protected $fillable = [
        'id',
        'run_id',
        'reference_id',
        'status',
        'external_amount_cents',
        'internal_amount_cents',
        'difference_cents',
        'currency',
        'details',
        'resolved',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'run_id');
    }

    public function getFormattedDifferenceAttribute(): string
    {
        return number_format($this->difference_cents / 100, 2, '.', ' ') . ' ' . $this->currency;
    }
}
