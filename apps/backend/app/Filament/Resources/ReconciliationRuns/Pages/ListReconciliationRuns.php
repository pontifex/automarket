<?php

namespace App\Filament\Resources\ReconciliationRuns\Pages;

use App\Filament\Resources\ReconciliationRuns\ReconciliationRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReconciliationRuns extends ListRecords
{
    protected static string $resource = ReconciliationRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
