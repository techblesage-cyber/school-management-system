<?php

namespace App\Filament\School\Resources\Guardians\Pages;

use App\Filament\School\Resources\Guardians\Actions\PortalAccessActions;
use App\Filament\School\Resources\Guardians\GuardianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuardian extends EditRecord
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...PortalAccessActions::all(),
            DeleteAction::make(),
        ];
    }
}
