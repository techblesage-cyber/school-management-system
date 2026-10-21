<?php

namespace App\Filament\School\Resources\Subjects\Schemas;

use App\Filament\School\Resources\Students\Schemas\StudentForm;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Toggle::make('in_aggregate')
                    ->label('Counts towards aggregate and division')
                    ->helperText('For PLE-style grading. Turn off for subjects like Religious Education or Kiswahili if your school leaves them out.')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }
}
