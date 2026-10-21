<?php

namespace App\Filament\School\Resources\Guardians\Pages;

use App\Filament\School\Resources\Guardians\Actions\PortalAccessActions;
use App\Filament\School\Resources\Guardians\GuardianResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Read-only page for staff who may look but not change (e.g. teachers).
 */
class ViewGuardian extends ViewRecord
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...PortalAccessActions::all(),
            EditAction::make(),
        ];
    }
}
