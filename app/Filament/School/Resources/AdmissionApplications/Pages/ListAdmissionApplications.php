<?php

namespace App\Filament\School\Resources\AdmissionApplications\Pages;

use App\Filament\School\Resources\AdmissionApplications\AdmissionApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdmissionApplications extends ListRecords
{
    protected static string $resource = AdmissionApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
