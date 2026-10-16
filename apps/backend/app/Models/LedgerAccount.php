<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LedgerAccount extends Model
{
    use HasUuids;

    protected $table = 'ledger_accounts';

    protected $fillable = [
        'id',
        'type',
        'currency',
        'owner_id',
        'balance_cents',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance_cents / 100, 2, '.', ' ') . ' ' . $this->currency;
    }
}
