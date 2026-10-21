<?php

namespace App\Filament\Admin\Resources\SmsMessages\Pages;

use App\Filament\Admin\Resources\SmsMessages\SmsMessageResource;
use App\Models\SmsMessage;
use Filament\Resources\Pages\ListRecords;

class ListSmsMessages extends ListRecords
{
    protected static string $resource = SmsMessageResource::class;

    public function getSubheading(): ?string
    {
        $month = SmsMessage::query()->where('status', 'sent')->where('created_at', '>=', now()->startOfMonth())->count();

        return "{$month} SMS sent this month across all schools. Filter by school to see one school's usage.";
    }
}
