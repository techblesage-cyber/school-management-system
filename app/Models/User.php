<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Schools this user works at, with their role in the pivot.
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Admin panel: Sharebility super admins only.
     * School panel: super admins, plus staff of at least one active school.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isSuperAdmin(),
            'school' => $this->isSuperAdmin() || $this->schools()->active()->exists(),
            default => false,
        };
    }

    /**
     * The schools offered in the school panel's switcher.
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperAdmin()) {
            return School::query()->active()->orderBy('name')->get();
        }

        return $this->schools()->active()->orderBy('name')->get();
    }

    /**
     * Checked on every school panel request, so staff can never open
     * another school's pages by changing the address.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof School || ! $tenant->is_active) {
            return false;
        }

        return $this->isSuperAdmin()
            || $this->schools()->whereKey($tenant->getKey())->exists();
    }
}
