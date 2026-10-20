<?php

namespace App\Filament\School\Pages\Tenancy;

use App\Enums\StaffRole;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
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

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->schools()
                ->whereKey($tenant->getKey())
                ->wherePivotIn('role', [StaffRole::Admin->value, StaffRole::HeadTeacher->value])
                ->exists();
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
