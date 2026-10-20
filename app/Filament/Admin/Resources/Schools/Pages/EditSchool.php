<?php

namespace App\Filament\Admin\Resources\Schools\Pages;

use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\Admin\Resources\Schools\Tables\SchoolsTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchool extends EditRecord
{
    protected static string $resource = SchoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SchoolsTable::openPortalAction(),
            DeleteAction::make()
                ->modalDescription('This permanently deletes the school and removes all its staff from it. This cannot be undone.'),
        ];
    }
}
