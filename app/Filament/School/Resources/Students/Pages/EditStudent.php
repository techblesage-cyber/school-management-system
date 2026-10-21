<?php

namespace App\Filament\School\Resources\Students\Pages;

use App\Filament\School\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StudentResource::feeStatementAction(),
            DeleteAction::make()
                ->modalDescription('Only for records added by mistake. For a student who has left, change their status instead.'),
        ];
    }
}
