<?php

namespace App\Filament\Admin\Resources\Schools\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SchoolForm
{
    /**
     * Words that can't be a school's address, because they clash with
     * common server subdomains once schools get their own subdomain.
     */
    public const RESERVED_SLUGS = ['www', 'admin', 'school', 'mail', 'webmail', 'cpanel', 'whm', 'ftp', 'api', 'app'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('School details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('School name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Web address name')
                            ->helperText('Lowercase letters, numbers and dashes. Used in the school\'s web address, so avoid changing it later.')
                            ->required()
                            ->maxLength(63)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->notIn(self::RESERVED_SLUGS)
                            ->unique(ignoreRecord: true),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('address')
                            ->maxLength(255),
                        TextInput::make('motto')
                            ->maxLength(255),
                        FileUpload::make('logo')
                            ->image()
                            ->disk('public')
                            ->directory('school-logos')
                            ->maxSize(2048),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive schools can\'t open their portal.')
                            ->default(true),
                    ]),
            ]);
    }
}
