<?php

namespace App\Filament\Resources\ReconciliationRuns;

use App\Filament\Resources\ReconciliationRuns\Pages\CreateReconciliationRun;
use App\Filament\Resources\ReconciliationRuns\Pages\EditReconciliationRun;
use App\Filament\Resources\ReconciliationRuns\Pages\ListReconciliationRuns;
use App\Filament\Resources\ReconciliationRuns\Schemas\ReconciliationRunForm;
use App\Filament\Resources\ReconciliationRuns\Tables\ReconciliationRunsTable;
use App\Models\ReconciliationRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReconciliationRunResource extends Resource
{
    protected static ?string $model = ReconciliationRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ReconciliationRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReconciliationRunsTable::configure($table);
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
            'index' => ListReconciliationRuns::route('/'),
            'create' => CreateReconciliationRun::route('/create'),
            'edit' => EditReconciliationRun::route('/{record}/edit'),
        ];
    }
}
