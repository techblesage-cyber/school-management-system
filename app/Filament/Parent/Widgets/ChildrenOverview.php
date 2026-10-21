<?php

namespace App\Filament\Parent\Widgets;

use App\Filament\Parent\Resources\Children\ChildResource;
use App\Filament\School\Pages\ReportCards;
use App\Filament\Shared\InitialsAvatars;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Support\Greeting;
use App\Support\Money;
use App\Support\ParentPortal;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The parent's home: a greeting, then a card for each child with their
 * class, latest result, attendance and fees. Tap a card for the details.
 */
class ChildrenOverview extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.parent-home';

    protected function getViewData(): array
    {
        $parent = Filament::auth()->user();

        $children = $parent instanceof ParentAccount
            ? ChildResource::getEloquentQuery()->orderBy('first_name')->get()
            : collect();

        return [
            'greeting' => Greeting::for($parent?->name),
            'school' => ParentPortal::school()?->name,
            'date' => now()->isoFormat('dddd D MMMM'),
            'children' => $children->map(function (Student $child): array {
                $latest = ParentPortal::latest($child);
                $attendance = ParentPortal::attendanceThisTerm($child);
                $balance = $child->feeBalance();

                return [
                    'id' => $child->getKey(),
                    'name' => $child->full_name,
                    'first' => $child->first_name,
                    'class' => $child->class_and_stream,
                    'photo' => $child->photoUrl() ?? InitialsAvatars::for($child->full_name),
                    'url' => ChildResource::getUrl('view', ['record' => $child]),
                    'result' => $latest ? $latest[0]->name.': '.ReportCards::summaryLine($latest[1]) : null,
                    'attendance' => $attendance ? "{$attendance['present']} days at school" : null,
                    'fees' => Money::balance($balance),
                    'owes' => $balance > 0,
                ];
            })->all(),
        ];
    }
}
