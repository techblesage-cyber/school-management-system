<?php

namespace App\Filament\Parent\Resources\Children\Pages;

use App\Filament\Parent\Resources\Children\ChildResource;
use Filament\Resources\Pages\ListRecords;

class ListChildren extends ListRecords
{
    protected static string $resource = ChildResource::class;
}
