<?php

namespace App\Auth;

use App\Support\SchoolAddress;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Finds the parent signing in. A phone number can have an account at more
 * than one school (each with its own password), so the password decides
 * which school's account opens. On a school's own subdomain only that
 * school's accounts are considered.
 */
class ParentAccountProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        $phone = $credentials['phone'] ?? null;

        if (blank($phone)) {
            return null;
        }

        $accounts = $this->newModelQuery()
            ->where('phone', $phone)
            ->when(SchoolAddress::fromRequest(), fn ($query, $school) => $query->where('school_id', $school->getKey()))
            ->orderBy('id')
            ->get();

        $password = $credentials['password'] ?? null;

        if ($accounts->count() > 1 && filled($password)) {
            return $accounts->first(fn (Authenticatable $account): bool => $this->hasher->check($password, $account->getAuthPassword()))
                ?? $accounts->first();
        }

        return $accounts->first();
    }
}
