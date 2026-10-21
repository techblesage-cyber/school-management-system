<?php

namespace App\Filament\School\Resources\Guardians\Pages;

use App\Filament\School\Resources\Guardians\Actions\PortalAccessActions;
use App\Filament\School\Resources\Guardians\GuardianResource;
use App\Models\Guardian;
use Filament\Resources\Pages\CreateRecord;

class CreateGuardian extends CreateRecord
{
    protected static string $resource = GuardianResource::class;

    /**
     * A new parent gets their parents' portal login straight away.
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        PortalAccessActions::welcome($record instanceof Guardian ? $record : null);
    }
}
