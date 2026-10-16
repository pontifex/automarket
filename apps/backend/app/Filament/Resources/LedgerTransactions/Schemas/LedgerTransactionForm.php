<?php

namespace App\Filament\Resources\LedgerTransactions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LedgerTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('idempotency_key')
                    ->required(),
                TextInput::make('reference_type')
                    ->required(),
                TextInput::make('reference_id')
                    ->required(),
                TextInput::make('currency')
                    ->required()
                    ->default('PLN'),
                TextInput::make('total_cents')
                    ->required()
                    ->numeric(),
            ]);
    }
}
