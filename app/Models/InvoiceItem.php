<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on an invoice. Negative amounts are discounts or bursaries.
 */
#[Fillable(['invoice_id', 'fee_item_id', 'description', 'amount'])]
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the invoice total in step with its lines
        static::saved(fn (InvoiceItem $item) => $item->invoice?->recalculateTotal());
        static::deleted(fn (InvoiceItem $item) => $item->invoice?->recalculateTotal());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }
}
