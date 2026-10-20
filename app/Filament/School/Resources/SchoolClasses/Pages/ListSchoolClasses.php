<?php

namespace App\Filament\School\Resources\SchoolClasses\Pages;

use App\Enums\ClassLevel;
use App\Filament\School\Resources\SchoolClasses\SchoolClassResource;
use App\Models\School;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSchoolClasses extends ListRecords
{
    protected static string $resource = SchoolClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->addStandardClassesAction(),
            CreateAction::make(),
        ];
    }

    /**
     * One-click setup of the usual Ugandan classes. Classes that already
     * exist are skipped, so it is safe to run again.
     */
    protected function addStandardClassesAction(): Action
    {
        $levels = collect(ClassLevel::cases())->filter(fn (ClassLevel $level): bool => $level->standardClasses() !== []);

        return Action::make('addStandardClasses')
            ->label('Add standard classes')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->modalSubmitActionLabel('Add classes')
            ->schema([
                CheckboxList::make('levels')
                    ->label('Which sections does your school have?')
                    ->options($levels->mapWithKeys(fn (ClassLevel $level): array => [$level->value => $level->getLabel()]))
                    ->descriptions($levels->mapWithKeys(fn (ClassLevel $level): array => [$level->value => implode(', ', $level->standardClasses())]))
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var School $school */
                $school = Filament::getTenant();
                $existing = $school->schoolClasses()->pluck('name')->map(fn (string $name): string => mb_strtolower($name));
                $added = 0;

                foreach ($data['levels'] as $levelValue) {
                    $level = ClassLevel::from($levelValue);

                    foreach ($level->standardClasses() as $position => $name) {
                        if ($existing->contains(mb_strtolower($name))) {
                            continue;
                        }

                        $school->schoolClasses()->create([
                            'name' => $name,
                            'level' => $level,
                            'sort_order' => $level->sortBase() + $position,
                        ]);
                        $added++;
                    }
                }

                Notification::make()
                    ->title($added > 0 ? "Added {$added} classes" : 'Those classes already exist')
                    ->body($added > 0 ? 'Open a class to add its streams.' : null)
                    ->success()
                    ->send();
            });
    }
}
