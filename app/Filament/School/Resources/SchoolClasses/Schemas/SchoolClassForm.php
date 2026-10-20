<?php

namespace App\Filament\School\Resources\SchoolClasses\Schemas;

use App\Enums\ClassLevel;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolClassForm
{
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
                    ]),
                Section::make('Streams')
                    ->description('For example East and West, or A and B. Leave empty if the class has no streams.')
                    ->schema([
                        Repeater::make('streams')
                            ->hiddenLabel()
                            ->relationship()
                            ->simple(
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(50)
                                    ->distinct(),
                            )
                            ->defaultItems(0)
                            ->addActionLabel('Add stream')
                            ->grid(3),
                    ]),
            ]);
    }
}
