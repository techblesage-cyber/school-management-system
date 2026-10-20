<?php

namespace App\Filament\School\Resources\Students\Pages;

use App\Filament\School\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;
}
