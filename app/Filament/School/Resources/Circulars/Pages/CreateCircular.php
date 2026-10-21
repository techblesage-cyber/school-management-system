<?php

namespace App\Filament\School\Resources\Circulars\Pages;

use App\Filament\School\Resources\Circulars\CircularResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCircular extends CreateRecord
{
    protected static string $resource = CircularResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
