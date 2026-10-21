<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SchoolAbility;
use App\Enums\StaffRole;
use App\Models\Concerns\HasPhoto;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'photo', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPhoto, Notifiable;

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
            'must_change_password' => 'boolean',
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

    /**
     * Subjects and classes this user teaches, at every school they work at.
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photoUrl();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Roles already looked up during this request, by school id.
     *
     * @var array<int, StaffRole|null>
     */
    protected array $rolesAtSchools = [];

    /**
     * This user's role at a school (the current portal school by default),
     * or null if they don't work there.
     */
    public function roleAt(School|int|null $school = null): ?StaffRole
    {
        $school ??= Filament::getTenant();
        $schoolId = $school instanceof School ? $school->getKey() : $school;

        if (! $schoolId) {
            return null;
        }

        if (! array_key_exists($schoolId, $this->rolesAtSchools)) {
            $role = $this->schools()->whereKey($schoolId)->value('school_user.role');
            $this->rolesAtSchools[$schoolId] = StaffRole::tryFrom((string) $role);
        }

        return $this->rolesAtSchools[$schoolId];
    }

    /**
     * Whether this user may do something at a school (the current portal
     * school by default). Super admins may do everything.
     */
    public function canInSchool(SchoolAbility $ability, School|int|null $school = null): bool
    {
        return $this->isSuperAdmin() || ($this->roleAt($school)?->can($ability) ?? false);
    }

    /**
     * Forget cached roles, e.g. after this user's role was changed.
     */
    public function forgetSchoolRoles(): static
    {
        $this->rolesAtSchools = [];

        return $this;
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
