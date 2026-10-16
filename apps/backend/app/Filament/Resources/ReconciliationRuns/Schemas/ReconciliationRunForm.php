<?php

namespace App\Filament\Resources\ReconciliationRuns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReconciliationRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('reconciled_at')
                    ->required(),
                TextInput::make('currency')
                    ->required()
                    ->default('PLN'),
                TextInput::make('matched_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('discrepancy_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_reconciled_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_discrepancy_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('status')
                    ->required()
                    ->default('BALANCED'),
            ]);
    }
}
