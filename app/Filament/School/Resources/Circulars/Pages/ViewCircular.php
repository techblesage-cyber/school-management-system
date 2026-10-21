<?php

namespace App\Filament\School\Resources\Circulars\Pages;

use App\Filament\School\Resources\Circulars\CircularResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCircular extends ViewRecord
{
    protected static string $resource = CircularResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CircularResource::smsAction(),
            EditAction::make(),
        ];
    }
}
