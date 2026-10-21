<?php

namespace App\Filament\School\Resources\Subjects\Schemas;

use App\Filament\School\Resources\Students\Schemas\StudentForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('name')
                    ->label('Subject')
                    ->placeholder('e.g. Mathematics')
                    ->required()
                    ->maxLength(100)
                    ->scopedUnique(modifyQueryUsing: StudentForm::withinSchool(...))
                    ->columnSpan(2),
                TextInput::make('code')
                    ->label('Code')
                    ->placeholder('e.g. 456')
                    ->helperText('Optional, e.g. the UNEB code.')
                    ->maxLength(20),
            ]);
    }
}
