<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A parent, guardian or sponsor. One guardian can have several children at
 * the same school, and each child can have several guardians.
 */
#[Fillable(['school_id', 'name', 'phone', 'alt_phone', 'email', 'occupation', 'address'])]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use BelongsToSchool, HasFactory;

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Phone numbers are stored without spaces or dashes, so the same number
     * typed differently ("0772 123 456", "0772-123456") is found again.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => static::cleanPhone($value));
    }

    protected function altPhone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => static::cleanPhone($value));
    }

    public static function cleanPhone(?string $value): ?string
    {
        return filled($value) ? preg_replace('/[^\d+]/', '', $value) : null;
    }

    /**
     * Finds a guardian in this school with the same phone number, matching
     * on the last 9 digits so "0772123456" and "+256772123456" are the same.
     */
    public static function findByPhone(int $schoolId, string $phone): ?self
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 9) {
            return null;
        }

        return static::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('phone', 'like', '%'.substr($digits, -9))
            ->first();
    }
}
