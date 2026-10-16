<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReconciliationRun extends Model
{
    use HasUuids;

    protected $table = 'reconciliation_runs';

    protected $fillable = [
        'id',
        'reconciled_at',
        'currency',
        'matched_count',
        'discrepancy_count',
        'total_reconciled_cents',
        'total_discrepancy_cents',
        'status',
    ];

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReconciliationDiscrepancy::class, 'run_id');
    }

    public function getFormattedTotalReconciledAttribute(): string
    {
        return number_format($this->total_reconciled_cents / 100, 2, '.', ' ') . ' ' . $this->currency;
    }

    public function getFormattedTotalDiscrepancyAttribute(): string
    {
        return number_format($this->total_discrepancy_cents / 100, 2, '.', ' ') . ' ' . $this->currency;
    }
}
