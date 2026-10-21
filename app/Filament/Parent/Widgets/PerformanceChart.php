<?php

namespace App\Filament\Parent\Widgets;

use App\Models\ParentAccount;
use App\Models\Student;
use App\Support\ParentPortal;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * A child's average mark term by term, from published report cards.
 * Shown on the child's page.
 */
class PerformanceChart extends ChartWidget
{
    protected ?string $heading = 'Average mark per term';

    protected ?string $maxHeight = '260px';

    public ?Student $record = null;

    protected static bool $isDiscovered = false;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $averages = $this->child() ? ParentPortal::averages($this->child()) : [];

        return [
            'datasets' => [
                [
                    'label' => 'Average %',
                    'data' => array_values($averages),
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_keys($averages),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['min' => 0, 'max' => 100]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    public function getDescription(): ?string
    {
        return $this->child() && count(ParentPortal::averages($this->child())) > 0
            ? null
            : 'Appears once the school publishes report cards.';
    }

    /**
     * The child, checked again here: widgets can be called on their own.
     */
    protected function child(): ?Student
    {
        $parent = Filament::auth()->user();

        return $this->record && $parent instanceof ParentAccount && $parent->hasChild($this->record) ? $this->record : null;
    }
}
