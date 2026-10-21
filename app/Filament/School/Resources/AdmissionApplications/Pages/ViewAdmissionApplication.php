<?php

namespace App\Filament\School\Resources\AdmissionApplications\Pages;

use App\Filament\School\Resources\AdmissionApplications\Actions\ApplicationActions;
use App\Filament\School\Resources\AdmissionApplications\AdmissionApplicationResource;
use App\Models\AdmissionApplication;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Read-only view, e.g. for enrolled applications, which can no longer change.
 */
class ViewAdmissionApplication extends ViewRecord
{
    protected static string $resource = AdmissionApplicationResource::class;

    public function getSubheading(): ?string
    {
        /** @var AdmissionApplication $record */
        $record = $this->getRecord();

        return "{$record->reference} · {$record->status->getLabel()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ApplicationActions::all(),
            EditAction::make(),
        ];
    }
}
