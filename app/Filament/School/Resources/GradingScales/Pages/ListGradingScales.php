<?php

namespace App\Filament\School\Resources\GradingScales\Pages;

use App\Filament\School\Resources\GradingScales\GradingScaleResource;
use App\Models\GradingScale;
use App\Models\School;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListGradingScales extends ListRecords
{
    protected static string $resource = GradingScaleResource::class;

    protected function getHeaderActions(): array
    {
        $standard = GradingScale::standardScales();

        return [
            Action::make('addStandardScales')
                ->label('Add standard scales')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->authorize('create', GradingScale::class)
                ->modalSubmitActionLabel('Add scales')
                ->schema([
                    CheckboxList::make('scales')
                        ->label('Which sections does your school have?')
                        ->options(array_combine(array_keys($standard), array_keys($standard)))
                        ->descriptions(collect($standard)
                            ->map(fn (array $scale): string => collect($scale['bands'])->map(fn (array $band): string => "{$band[0]} from {$band[1]}%")->implode(', '))
                            ->all())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var School $school */
                    $school = Filament::getTenant();
                    $added = collect($data['scales'])
                        ->filter(fn (string $name): bool => GradingScale::addStandard($school, $name) !== null)
                        ->count();

                    Notification::make()
                        ->title($added > 0 ? "Added {$added} grading ".str('scale')->plural($added) : 'Those scales already exist')
                        ->body($added > 0 ? 'Now choose a scale for each class under Setup → Classes & streams. Change any grade boundaries your school does differently.' : null)
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
