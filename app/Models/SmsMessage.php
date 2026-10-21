<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'to', 'message', 'purpose', 'status', 'provider_id', 'cost', 'error', 'sent_by', 'sent_at'])]
class SmsMessage extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Number of SMS parts (160 characters each, 153 when split).
     */
    public function parts(): int
    {
        $length = mb_strlen($this->message);

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
