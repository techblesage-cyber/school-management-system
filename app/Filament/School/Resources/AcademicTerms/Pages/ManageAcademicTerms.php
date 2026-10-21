<?php

namespace App\Filament\School\Resources\AcademicTerms\Pages;

use App\Filament\School\Resources\AcademicTerms\AcademicTermResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAcademicTerms extends ManageRecords
{
    protected static string $resource = AcademicTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
