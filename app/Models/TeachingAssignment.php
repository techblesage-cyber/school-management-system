<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One subject taught by one teacher to one class, or to one stream of it.
 */
#[Fillable(['school_id', 'user_id', 'subject_id', 'school_class_id', 'stream_id'])]
class TeachingAssignment extends Model
{
    use BelongsToSchool;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * "S.1" for the whole class, "S.1 East" for one stream.
     */
    protected function classLabel(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->schoolClass?->name.' '.$this->stream?->name));
    }

    /**
     * Option value used in forms: "c:5" for class 5, "s:12" for stream 12.
     */
    protected function classKey(): Attribute
    {
        return Attribute::get(fn (): string => $this->stream_id ? "s:{$this->stream_id}" : "c:{$this->school_class_id}");
    }

    /**
     * A teacher's subjects at a school, as form rows:
     * [['subject_id' => 3, 'classes' => ['c:5', 's:12']], ...]
     *
     * @return array<int, array{subject_id: int, classes: array<int, string>}>
     */
    public static function rowsFor(School $school, User $user): array
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $rows, int $subjectId): array => [
                'subject_id' => $subjectId,
                'classes' => $rows->map->class_key->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Replaces a teacher's subjects at a school with the given form rows.
     * Values that don't belong to the school are ignored.
     *
     * @param  array<int|string, array{subject_id?: mixed, classes?: array<int, string>}>  $rows
     */
    public static function syncFor(School $school, User $user, array $rows): void
    {
        $subjectIds = $school->subjects()->pluck('id')->all();
        $classIds = $school->schoolClasses()->pluck('id')->all();
        $streams = Stream::query()->whereIn('school_class_id', $classIds)->pluck('school_class_id', 'id')->all();

        $wanted = [];

        foreach ($rows as $row) {
            $subjectId = (int) ($row['subject_id'] ?? 0);

            if (! in_array($subjectId, $subjectIds, true)) {
                continue;
            }

            $wholeClasses = [];
            $chosenStreams = [];

            foreach ($row['classes'] ?? [] as $key) {
                [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);
                $id = (int) $id;

                if ($type === 'c' && in_array($id, $classIds, true)) {
                    $wholeClasses[$id] = true;
                } elseif ($type === 's' && isset($streams[$id])) {
                    $chosenStreams[$id] = $streams[$id];
                }
            }

            foreach (array_keys($wholeClasses) as $classId) {
                $wanted["{$subjectId}-{$classId}-"] = [$subjectId, $classId, null];
            }

            // A stream is only needed when its whole class wasn't chosen
            foreach ($chosenStreams as $streamId => $classId) {
                if (! isset($wholeClasses[$classId])) {
                    $wanted["{$subjectId}-{$classId}-{$streamId}"] = [$subjectId, $classId, $streamId];
                }
            }
        }

        DB::transaction(function () use ($school, $user, $wanted): void {
            static::query()
                ->withoutGlobalScopes()
                ->where('school_id', $school->getKey())
                ->where('user_id', $user->getKey())
                ->delete();

            foreach ($wanted as [$subjectId, $classId, $streamId]) {
                static::query()->withoutGlobalScopes()->create([
                    'school_id' => $school->getKey(),
                    'user_id' => $user->getKey(),
                    'subject_id' => $subjectId,
                    'school_class_id' => $classId,
                    'stream_id' => $streamId,
                ]);
            }
        });
    }

    /**
     * Class and stream choices for a school, grouped by class:
     * ['S.1' => ['c:5' => 'S.1 (all streams)', 's:12' => 'S.1 East', ...], ...]
     *
     * @return array<string, array<string, string>>
     */
    public static function classOptions(School $school): array
    {
        return $school->schoolClasses()
            ->with('streams')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (SchoolClass $class): array => [
                $class->name => [
                    "c:{$class->id}" => $class->streams->isEmpty() ? $class->name : "{$class->name} (all streams)",
                    ...$class->streams->mapWithKeys(fn (Stream $stream): array => ["s:{$stream->id}" => "{$class->name} {$stream->name}"])->all(),
                ],
            ])
            ->all();
    }
}
