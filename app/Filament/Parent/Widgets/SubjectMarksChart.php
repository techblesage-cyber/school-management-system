<?php

namespace App\Filament\Parent\Widgets;

use App\Models\ParentAccount;
use App\Models\Student;
use App\Support\ParentPortal;
use App\Support\Results\SubjectResult;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

/**
 * Each subject's mark on the latest published report card.
 */
class SubjectMarksChart extends ChartWidget
{
    protected ?string $maxHeight = '320px';

    public ?Student $record = null;

    protected static bool $isDiscovered = false;

    public function getHeading(): ?string
    {
        $latest = $this->child() ? ParentPortal::latest($this->child()) : null;

        return $latest ? 'Marks by subject · '.$latest[0]->name : 'Marks by subject';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $subjects = collect(($this->child() ? ParentPortal::latest($this->child()) : null)[1]->subjects ?? [])
            ->filter(fn (SubjectResult $subject): bool => $subject->final !== null);

        return [
            'datasets' => [
                [
                    'label' => 'Mark %',
                    'data' => $subjects->map(fn (SubjectResult $subject): float => $subject->final)->values()->all(),
                    'backgroundColor' => $subjects->map(fn (SubjectResult $subject): string => match (true) {
                        $subject->final >= 70 => '#059669',
                        $subject->final >= 50 => '#d97706',
                        default => '#dc2626',
                    })->values()->all(),
                ],
            ],
            'labels' => $subjects->map(fn (SubjectResult $subject): string => Str::limit($subject->subject->name, 18))->values()->all(),
        ];
    }

    protected function getOptions(): array
    {
        // Sideways bars, so long subject names stay readable on phones
        return [
            'indexAxis' => 'y',
            'aspectRatio' => 1,
            'scales' => [
                'x' => ['min' => 0, 'max' => 100],
                'y' => ['ticks' => ['autoSkip' => false]],
            ],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function child(): ?Student
    {
        $parent = Filament::auth()->user();

        return $this->record && $parent instanceof ParentAccount && $parent->hasChild($this->record) ? $this->record : null;
    }
}
