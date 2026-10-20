<?php

namespace App\Filament\School\Resources\Students\Schemas;

use App\Enums\Gender;
use App\Enums\Residence;
use App\Enums\StudentStatus;
use App\Models\SchoolClass;
use App\Models\Stream;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal details')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('last_name')
                            ->label('Surname')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('first_name')
                            ->label('First name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('other_names')
                            ->label('Other names')
                            ->maxLength(100),
                        Select::make('gender')
                            ->options(Gender::class)
                            ->required(),
                        DatePicker::make('date_of_birth')
                            ->label('Date of birth')
                            ->maxDate(now()),
                        TextInput::make('nationality')
                            ->default('Ugandan')
                            ->maxLength(50),
                        TextInput::make('lin')
                            ->label('LIN')
                            ->helperText('Learner Identification Number, if issued.')
                            ->maxLength(30)
                            ->scopedUnique(modifyQueryUsing: self::withinSchool(...)),
                        TextInput::make('home_address')
                            ->label('Home address / village')
                            ->maxLength(255)
                            ->columnSpan(2),
                        FileUpload::make('photo')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('student-photos')
                            ->maxSize(2048),
                    ]),
                Section::make('Enrolment')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('admission_number')
                            ->label('Admission number')
                            ->placeholder('Generated automatically')
                            ->helperText('Leave empty to get the next number, e.g. '.now()->year.'/0001.')
                            ->maxLength(30)
                            ->scopedUnique(modifyQueryUsing: self::withinSchool(...)),
                        DatePicker::make('admission_date')
                            ->label('Admission date')
                            ->default(now())
                            ->required(),
                        Select::make('status')
                            ->options(StudentStatus::class)
                            ->default(StudentStatus::Active)
                            ->required(),
                        self::classSelect(),
                        self::streamSelect(),
                        Select::make('residence')
                            ->options(Residence::class)
                            ->default(Residence::Day)
                            ->required(),
                        TextInput::make('previous_school')
                            ->label('Previous school')
                            ->maxLength(255),
                        TextInput::make('entry_index_number')
                            ->label('PLE / UCE index no.')
                            ->maxLength(30),
                    ]),
                Section::make('Notes')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * Limits a uniqueness check to the current school's records.
     */
    public static function withinSchool(Builder $query): Builder
    {
        return $query->withoutGlobalScopes()->where('school_id', Filament::getTenant()?->getKey());
    }

    /**
     * The school's classes, in their usual order. When the form also has a
     * stream field, changing class clears the stream.
     */
    public static function classSelect(bool $withStream = true): Select
    {
        $select = Select::make('school_class_id')
            ->label('Class')
            ->options(fn (): array => SchoolClass::query()
                ->where('school_id', Filament::getTenant()?->getKey())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $belongsToSchool = SchoolClass::query()
                    ->withoutGlobalScopes()
                    ->whereKey($value)
                    ->where('school_id', Filament::getTenant()?->getKey())
                    ->exists();

                if (filled($value) && ! $belongsToSchool) {
                    $fail('Choose one of this school\'s classes.');
                }
            })
            ->required();

        if ($withStream) {
            $select->live()->afterStateUpdated(fn (Set $set) => $set('stream_id', null));
        }

        return $select;
    }

    /**
     * Streams of the chosen class. Hidden when that class has no streams.
     */
    public static function streamSelect(): Select
    {
        $streamsOf = fn (Get $get): Builder => Stream::query()->where('school_class_id', $get('school_class_id') ?: 0);

        return Select::make('stream_id')
            ->label('Stream')
            ->options(fn (Get $get): array => $streamsOf($get)->orderBy('name')->pluck('name', 'id')->all())
            ->visible(fn (Get $get): bool => $streamsOf($get)->exists())
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $streamsOf): void {
                if (filled($value) && ! $streamsOf($get)->whereKey($value)->exists()) {
                    $fail('Choose a stream of the selected class.');
                }
            });
    }
}
