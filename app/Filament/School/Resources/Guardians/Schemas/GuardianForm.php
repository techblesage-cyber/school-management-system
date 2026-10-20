<?php

namespace App\Filament\School\Resources\Guardians\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GuardianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact details')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(self::fields()),
            ]);
    }

    /**
     * Shared with the "Parents & guardians" tab on a student.
     *
     * @return array<int, TextInput>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Full name')
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->tel()
                ->required()
                ->maxLength(30),
            TextInput::make('alt_phone')
                ->label('Other phone')
                ->tel()
                ->maxLength(30),
            TextInput::make('email')
                ->email()
                ->maxLength(255),
            TextInput::make('occupation')
                ->maxLength(100),
            TextInput::make('address')
                ->maxLength(255),
        ];
    }
}
