<?php

namespace App\Filament\Components;

use Filament\Forms\Components\FileUpload;

/**
 * Passport photo upload used for students, parents and staff. Photos are
 * cropped to passport shape (35 × 45 mm, i.e. 7:9) and shrunk in the browser
 * before upload, so each one takes about 50–100 KB on the server.
 */
class PassportPhoto
{
    public static function make(string $directory, string $name = 'photo'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Passport photo')
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->imageEditor()
            ->imageEditorAspectRatios(['7:9'])
            ->imageAspectRatio('7:9')
            ->automaticallyCropImagesToAspectRatio()
            ->automaticallyResizeImagesToWidth('420')
            ->automaticallyResizeImagesToHeight('540')
            ->imagePreviewHeight('180')
            ->maxSize(4096)
            ->helperText('Plain background, face clearly visible. You can crop it after choosing.');
    }
}
