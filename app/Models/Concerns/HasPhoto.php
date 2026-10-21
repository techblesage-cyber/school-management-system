<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * For records with a passport photo in the "photo" column. Removes the old
 * file when a photo is replaced or the record is deleted, so unused photos
 * don't fill up the server's storage.
 */
trait HasPhoto
{
    public static function bootHasPhoto(): void
    {
        static::updated(function (self $model): void {
            $old = $model->getOriginal('photo');

            if ($model->wasChanged('photo') && filled($old)) {
                Storage::disk('public')->delete($old);
            }
        });

        static::deleted(function (self $model): void {
            if (filled($model->photo)) {
                Storage::disk('public')->delete($model->photo);
            }
        });
    }

    public function photoUrl(): ?string
    {
        return filled($this->photo) ? Storage::disk('public')->url($this->photo) : null;
    }
}
