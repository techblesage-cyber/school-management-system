<?php

namespace App\Filament\Parent\Resources\Children\Pages;

use App\Filament\Parent\Resources\Children\ChildResource;
use App\Filament\Parent\Widgets\PerformanceChart;
use App\Filament\Parent\Widgets\SubjectMarksChart;
use Filament\Resources\Pages\ViewRecord;

class ViewChild extends ViewRecord
{
    protected static string $resource = ChildResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->full_name;
    }

    protected function getFooterWidgets(): array
    {
        return [
            PerformanceChart::make(['record' => $this->getRecord()]),
            SubjectMarksChart::make(['record' => $this->getRecord()]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
