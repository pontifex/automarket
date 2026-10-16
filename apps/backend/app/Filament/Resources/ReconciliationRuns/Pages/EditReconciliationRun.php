<?php

namespace App\Filament\Resources\ReconciliationRuns\Pages;

use App\Filament\Resources\ReconciliationRuns\ReconciliationRunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReconciliationRun extends EditRecord
{
    protected static string $resource = ReconciliationRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
