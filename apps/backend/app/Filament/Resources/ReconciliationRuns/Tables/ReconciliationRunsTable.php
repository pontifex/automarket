<?php

namespace App\Filament\Resources\ReconciliationRuns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReconciliationRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID'),
                TextColumn::make('reconciled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('matched_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('discrepancy_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_reconciled_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_discrepancy_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
