<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * A parent's login to one school's parents' portal. Signs in with their
 * phone number. Sees the children linked to them as parent or guardian at
 * that school. A parent with children at two schools has two accounts, each
 * with its own password, so one school's login never opens the other's.
 */
#[Fillable(['school_id', 'name', 'phone', 'email', 'password', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class ParentAccount extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => PhoneNumber::normalize($value));
    }

    /**
     * Their parent/guardian records at this school.
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /**
     * Their children at this account's school, while the school is active.
     */
    public function childrenQuery(): Builder
    {
        return Student::query()
            ->withoutGlobalScopes()
            ->where('students.school_id', $this->school_id)
            ->whereHas('guardians', fn (Builder $query) => $query->withoutGlobalScopes()->where('guardians.parent_account_id', $this->getKey()))
            ->whereHas('school', fn (Builder $query) => $query->where('is_active', true));
    }

    /**
     * @return Collection<int, Student>
     */
    public function children(): Collection
    {
        return $this->childrenQuery()->get();
    }

    public function hasChild(Student|int $student): bool
    {
        return $this->childrenQuery()->whereKey($student instanceof Student ? $student->getKey() : $student)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'parent' && $this->childrenQuery()->exists();
    }

    /**
     * Where the parent's mail goes (logins, new passwords).
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    /**
     * An easy-to-read temporary password, e.g. "kx7m-p2q9".
     */
    public static function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $pick = fn (int $length): string => collect(range(1, $length))->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');

        return $pick(4).'-'.$pick(4);
    }

    /**
     * Gives a parent record portal access: links it to this school's account
     * for its phone number (creating one if needed). Returns the account and,
     * when a new password was set, that password to give to the parent. They
     * choose their own password the first time they sign in.
     *
     * @return array{0: self, 1: string|null}
     */
    public static function grantTo(Guardian $guardian, bool $resetPassword = false): array
    {
        $phone = PhoneNumber::normalize($guardian->phone);
        abort_if(blank($phone), 422, 'This parent has no phone number.');

        $account = static::query()->where('school_id', $guardian->school_id)->where('phone', $phone)->first();
        $password = null;

        if (! $account) {
            $password = static::temporaryPassword();
            $account = static::query()->create([
                'school_id' => $guardian->school_id,
                'name' => $guardian->name,
                'phone' => $phone,
                'email' => $guardian->email,
                'password' => $password,
                'must_change_password' => true,
            ]);
        } elseif ($resetPassword) {
            $password = static::temporaryPassword();
            $account->update(['password' => $password, 'must_change_password' => true]);
        }

        if (blank($account->email) && filled($guardian->email)) {
            $account->update(['email' => $guardian->email]);
        }

        $guardian->forceFill(['parent_account_id' => $account->getKey()])->save();

        return [$account, $password];
    }

    public function getFilamentName(): string
    {
        return (string) Str::of($this->name)->trim();
    }
}
