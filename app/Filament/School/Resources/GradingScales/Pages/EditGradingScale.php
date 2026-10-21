<?php

namespace App\Filament\School\Resources\GradingScales\Pages;

use App\Filament\School\Resources\GradingScales\GradingScaleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGradingScale extends EditRecord
{
    protected static string $resource = GradingScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
