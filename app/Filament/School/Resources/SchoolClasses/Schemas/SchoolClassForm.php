<?php

namespace App\Filament\School\Resources\SchoolClasses\Schemas;

use App\Enums\ClassLevel;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use App\Models\GradingScale;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolClassForm
{
    /**
     * Choose one of this school's staff.
     */
    public static function staffSelect(string $name): Select
    {
        $staff = fn (): array => User::query()
            ->whereHas('schools', fn ($query) => $query->whereKey(Filament::getTenant()?->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return Select::make($name)
            ->options($staff)
            ->in(fn (): array => array_keys($staff()))
            ->searchable();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Class')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Class name')
                            ->placeholder('e.g. S.1 or P.5')
                            ->required()
                            ->maxLength(50)
                            ->scopedUnique(modifyQueryUsing: StudentForm::withinSchool(...)),
                        Select::make('level')
                            ->options(ClassLevel::class)
                            ->default(ClassLevel::Primary)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Position in lists')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Lower numbers are listed first.'),
                        Select::make('grading_scale_id')
                            ->label('Grading scale')
                            ->options(fn (): array => GradingScale::query()
                                ->where('school_id', Filament::getTenant()?->getKey())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->in(fn (): array => GradingScale::query()->where('school_id', Filament::getTenant()?->getKey())->pluck('id')->all())
                            ->placeholder('None yet')
                            ->helperText('How marks become grades on report cards. Set scales up under Setup → Grading scales.'),
                        self::staffSelect('class_teacher_id')
                            ->label('Class teacher')
                            ->helperText('Writes the class teacher\'s comment on report cards and can take attendance.')
                            ->columnSpan(2),
                    ]),
                Section::make('Streams')
                    ->description('For example East and West, or A and B. Leave empty if the class has no streams.')
                    ->schema([
                        Repeater::make('streams')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Stream')
                                    ->required()
                                    ->maxLength(50)
                                    ->distinct(),
                                self::staffSelect('class_teacher_id')
                                    ->label('Class teacher')
                                    ->placeholder('Same as the class'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add stream')
                            ->grid(['md' => 2]),
                    ]),
            ]);
    }
}
