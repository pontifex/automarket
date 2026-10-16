<?php

namespace App\Filament\Resources\LedgerTransactions\Pages;

use App\Filament\Resources\LedgerTransactions\LedgerTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLedgerTransaction extends CreateRecord
{
    protected static string $resource = LedgerTransactionResource::class;
}
