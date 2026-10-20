<?php

namespace App\Filament\School\Resources\AdmissionApplications\Schemas;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdmissionApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Applicant')
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
                        StudentForm::classSelect(withStream: false)
                            ->label('Class applied for')
                            ->required(false),
                        TextInput::make('previous_school')
                            ->label('Previous school')
                            ->maxLength(255),
                        TextInput::make('entry_index_number')
                            ->label('PLE / UCE index no.')
                            ->maxLength(30),
                    ]),
                Section::make('Parent / guardian')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('guardian_name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('guardian_phone')
                            ->label('Phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        TextInput::make('guardian_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Select::make('guardian_relationship')
                            ->label('Relationship to applicant')
                            ->options(GuardianRelationship::class)
                            ->default(GuardianRelationship::Guardian->value)
                            ->required(),
                    ]),
                Section::make('Application')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('applied_on')
                            ->label('Date received')
                            ->default(now())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes (interview, documents received...)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
