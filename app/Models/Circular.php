<?php

namespace App\Models;

use App\Enums\CircularAudience;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A letter or notice. Parents see it in the parents' portal once
 * published; staff see staff circulars on their dashboard.
 */
#[Fillable(['school_id', 'title', 'body', 'audience', 'attachment', 'attachment_name', 'published_at', 'created_by'])]
class Circular extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'audience' => CircularAudience::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Circular $circular): void {
            $circular->created_by ??= auth()->id();
        });

        // Attachments are private files: remove them with the circular
        static::updated(function (Circular $circular): void {
            if ($circular->wasChanged('attachment') && filled($old = $circular->getOriginal('attachment'))) {
                Storage::disk('local')->delete($old);
            }
        });

        static::deleted(function (Circular $circular): void {
            if (filled($circular->attachment)) {
                Storage::disk('local')->delete($circular->attachment);
            }
        });
    }

    /**
     * Classes whose parents receive it. None means every class.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeForStaff(Builder $query): Builder
    {
        return $query->whereIn('audience', [CircularAudience::Staff, CircularAudience::Everyone]);
    }

    /**
     * Published circulars for the parents of these students: their schools,
     * for parents, and either for every class or for one of their classes.
     *
     * @param  iterable<Student>  $students
     */
    public function scopeForParentsOf(Builder $query, iterable $students): Builder
    {
        $students = collect($students);

        return $query
            ->published()
            ->whereIn('audience', [CircularAudience::Parents, CircularAudience::Everyone])
            ->where(function (Builder $query) use ($students): void {
                $query->whereRaw('1 = 0');

                foreach ($students->groupBy('school_id') as $schoolId => $children) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('circulars.school_id', $schoolId)
                        ->where(fn (Builder $query) => $query
                            ->whereDoesntHave('classes')
                            ->orWhereHas('classes', fn (Builder $query) => $query->whereKey($children->pluck('school_class_id')->filter()->all()))));
                }
            });
    }

    /**
     * The circular's text with anything unsafe removed.
     */
    public function safeBody(): string
    {
        return Str::sanitizeHtml((string) $this->body);
    }

    /**
     * A plain-text preview, e.g. for an SMS or a list.
     */
    public function excerpt(int $length = 140): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) $this->body)))), $length);
    }
}
