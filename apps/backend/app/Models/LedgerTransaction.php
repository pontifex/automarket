<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LedgerTransaction extends Model
{
    use HasUuids;

    protected $table = 'ledger_transactions';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'idempotency_key',
        'reference_type',
        'reference_id',
        'currency',
        'total_cents',
        'created_at',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_cents / 100, 2, '.', ' ') . ' ' . $this->currency;
    }
}
