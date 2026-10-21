<?php

namespace App\Filament\School\Pages\Tenancy;

use App\Enums\SchoolAbility;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * "School profile" in the school switcher menu: the school's own details.
 */
class EditSchoolProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'School profile';
    }

    /**
     * Only Sharebility super admins and the school's administrator or
     * head teacher may change these details.
     */
    public static function canView(Model $tenant): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::EditSchoolProfile, $tenant->getKey());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('School details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('School name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('motto')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('show_positions')
                            ->label('Show class positions on report cards')
                            ->default(true),
                        Toggle::make('report_shows_fees')
                            ->label('Show fees balance on report cards')
                            ->default(true),
                        Toggle::make('sms_payment_receipts')
                            ->label('Text parents a receipt for each payment')
                            ->default(true)
                            ->visible(fn (): bool => (bool) Filament::getTenant()?->sms_enabled),
                        FileUpload::make('logo')
                            ->image()
                            ->disk('public')
                            ->directory('school-logos')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
