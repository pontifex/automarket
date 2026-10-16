<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerEntry extends Model
{
    use HasUuids;

    protected $table = 'ledger_entries';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'transaction_id',
        'account_id',
        'type',
        'amount_cents',
        'currency',
        'description',
        'created_at',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->type === 'DEBIT' ? '+' : '-';
        return $prefix . number_format($this->amount_cents / 100, 2, '.', ' ') . ' ' . $this->currency;
    }
}
