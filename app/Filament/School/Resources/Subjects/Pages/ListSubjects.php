<?php

namespace App\Filament\School\Resources\Subjects\Pages;

use App\Enums\ClassLevel;
use App\Filament\School\Resources\Subjects\SubjectResource;
use App\Models\School;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->addStandardSubjectsAction(),
            CreateAction::make(),
        ];
    }

    /**
     * Adds the usual subjects for the chosen sections. Subjects that already
     * exist are skipped, so it is safe to run again.
     */
    protected function addStandardSubjectsAction(): Action
    {
        $levels = collect(ClassLevel::cases())->filter(fn (ClassLevel $level): bool => $level->standardSubjects() !== []);

        return Action::make('addStandardSubjects')
            ->label('Add standard subjects')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->authorize('create', Subject::class)
            ->modalSubmitActionLabel('Add subjects')
            ->schema([
                CheckboxList::make('levels')
                    ->label('Which sections does your school have?')
                    ->options($levels->mapWithKeys(fn (ClassLevel $level): array => [$level->value => $level->getLabel()]))
                    ->descriptions($levels->mapWithKeys(fn (ClassLevel $level): array => [$level->value => implode(', ', $level->standardSubjects())]))
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var School $school */
                $school = Filament::getTenant();
                $existing = $school->subjects()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all();
                $added = 0;

                foreach ($data['levels'] as $levelValue) {
                    foreach (ClassLevel::from($levelValue)->standardSubjects() as $name) {
                        if (in_array(mb_strtolower($name), $existing, true)) {
                            continue;
                        }

                        $school->subjects()->create(['name' => $name]);
                        $existing[] = mb_strtolower($name);
                        $added++;
                    }
                }

                Notification::make()
                    ->title($added > 0 ? "Added {$added} subjects" : 'Those subjects already exist')
                    ->body($added > 0 ? 'Remove any your school doesn\'t teach.' : null)
                    ->success()
                    ->send();
            });
    }
}
