<?php

namespace App\Filament\Resources\LedgerTransactions;

use App\Filament\Resources\LedgerTransactions\Pages\CreateLedgerTransaction;
use App\Filament\Resources\LedgerTransactions\Pages\EditLedgerTransaction;
use App\Filament\Resources\LedgerTransactions\Pages\ListLedgerTransactions;
use App\Filament\Resources\LedgerTransactions\Schemas\LedgerTransactionForm;
use App\Filament\Resources\LedgerTransactions\Tables\LedgerTransactionsTable;
use App\Models\LedgerTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LedgerTransactionResource extends Resource
{
    protected static ?string $model = LedgerTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LedgerTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LedgerTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLedgerTransactions::route('/'),
            'create' => CreateLedgerTransaction::route('/create'),
            'edit' => EditLedgerTransaction::route('/{record}/edit'),
        ];
    }
}
