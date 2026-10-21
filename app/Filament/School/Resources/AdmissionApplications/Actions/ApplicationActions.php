<?php

namespace App\Filament\School\Resources\AdmissionApplications\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\Residence;
use App\Filament\Components\PassportPhoto;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use App\Filament\School\Resources\Students\StudentResource;
use App\Models\AdmissionApplication;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The decisions a school makes on an application: accept, decline, enrol.
 */
class ApplicationActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::accept(),
            self::reject(),
            self::enrol(),
            self::viewStudent(),
        ];
    }

    public static function accept(): Action
    {
        return Action::make('accept')
            ->authorize('decide')
            ->label('Accept')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('info')
            ->requiresConfirmation()
            ->modalDescription('Mark this applicant as admitted. You can enrol them as a student now or later.')
            ->visible(fn (AdmissionApplication $record): bool => $record->status === ApplicationStatus::Pending)
            ->action(function (AdmissionApplication $record): void {
                $record->update(['status' => ApplicationStatus::Accepted]);

                Notification::make()->title("{$record->full_name} accepted")->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->authorize('decide')
            ->label('Not admitted')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Mark as not admitted?')
            ->visible(fn (AdmissionApplication $record): bool => $record->canBeEnrolled())
            ->action(function (AdmissionApplication $record): void {
                $record->update(['status' => ApplicationStatus::Rejected]);

                Notification::make()->title("{$record->full_name} marked as not admitted")->success()->send();
            });
    }

    public static function enrol(): Action
    {
        return Action::make('enrol')
            ->authorize('decide')
            ->label('Enrol')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('success')
            ->modalHeading(fn (AdmissionApplication $record): string => "Enrol {$record->full_name}")
            ->modalDescription('Creates the student record with the next admission number and links the parent.')
            ->modalSubmitActionLabel('Enrol student')
            ->visible(fn (AdmissionApplication $record): bool => $record->canBeEnrolled())
            ->fillForm(fn (AdmissionApplication $record): array => [
                'school_class_id' => $record->school_class_id,
                'residence' => Residence::Day->value,
                'admission_date' => now()->toDateString(),
            ])
            ->schema([
                StudentForm::classSelect(),
                StudentForm::streamSelect(),
                Select::make('residence')
                    ->options(Residence::class)
                    ->required(),
                DatePicker::make('admission_date')
                    ->label('Admission date')
                    ->required(),
                PassportPhoto::make('student-photos')
                    ->label('Student\'s passport photo'),
            ])
            ->action(function (AdmissionApplication $record, array $data): void {
                $student = $record->enrol($data);

                Notification::make()
                    ->title("{$student->full_name} enrolled")
                    ->body("Admission number {$student->admission_number}")
                    ->success()
                    ->actions([
                        Action::make('open')
                            ->label('Open student')
                            ->url(StudentResource::getUrl('view', ['record' => $student])),
                    ])
                    ->send();
            });
    }

    public static function viewStudent(): Action
    {
        return Action::make('viewStudent')
            ->label('Open student')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (AdmissionApplication $record): bool => $record->student_id !== null)
            ->url(fn (AdmissionApplication $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]));
    }
}
