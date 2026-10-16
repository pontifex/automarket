<?php

namespace App\Filament\Resources\LedgerAccounts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LedgerAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->required(),
                TextInput::make('currency')
                    ->required()
                    ->default('PLN'),
                TextInput::make('owner_id'),
                TextInput::make('balance_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
