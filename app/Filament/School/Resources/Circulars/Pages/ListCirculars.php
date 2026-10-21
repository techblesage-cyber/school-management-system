<?php

namespace App\Filament\School\Resources\Circulars\Pages;

use App\Filament\School\Resources\Circulars\CircularResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCirculars extends ListRecords
{
    protected static string $resource = CircularResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New circular'),
        ];
    }
}
