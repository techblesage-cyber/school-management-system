<?php

namespace App\Filament\School\Resources\Assessments\Pages;

use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\Assessments\AssessmentResource;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAssessments extends ManageRecords
{
    protected static string $resource = AssessmentResource::class;

    public function getSubheading(): ?string
    {
        return 'Each term\'s exams. A subject\'s term mark combines them by weight; an exam a student missed is left out rather than counted as zero.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addUsualExams')
                ->label('Add the usual exams')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->authorize('create', Assessment::class)
                ->modalDescription('Adds Beginning of term, Mid-term and End of term, each out of 100 and counted equally. Change the weights afterwards if End of term should count more.')
                ->modalSubmitActionLabel('Add exams')
                ->schema([
                    FeeFields::term(),
                ])
                ->action(function (array $data): void {
                    $term = AcademicTerm::query()->withoutGlobalScopes()
                        ->where('school_id', Filament::getTenant()->getKey())
                        ->findOrFail($data['academic_term_id']);

                    $existing = $term->assessments()->withoutGlobalScopes()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all();
                    $added = 0;

                    foreach (['Beginning of term', 'Mid-term', 'End of term'] as $order => $name) {
                        if (in_array(mb_strtolower($name), $existing, true)) {
                            continue;
                        }

                        Assessment::query()->withoutGlobalScopes()->create([
                            'school_id' => $term->school_id,
                            'academic_term_id' => $term->getKey(),
                            'name' => $name,
                            'max_score' => 100,
                            'weight' => 100,
                            'sort_order' => $order + 1,
                        ]);
                        $added++;
                    }

                    Notification::make()
                        ->title($added > 0 ? "Added {$added} exams to {$term->name}" : 'Those exams already exist')
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label('Add exam'),
        ];
    }
}
