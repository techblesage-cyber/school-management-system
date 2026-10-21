<?php

namespace App\Support;

use NumberFormatter;

/**
 * Amounts are stored as whole shillings. These helpers format them the same
 * way everywhere: on screen, on receipts and on statements.
 */
class Money
{
    public static function currency(): string
    {
        return (string) config('school.currency', 'UGX');
    }

    /**
     * "UGX 1,250,000"
     */
    public static function format(int|float|string|null $amount): string
    {
        $amount = (int) round((float) $amount);
        $sign = $amount < 0 ? '-' : '';

        return $sign.static::currency().' '.number_format(abs($amount));
    }

    /**
     * A balance: "UGX 50,000", "Cleared", or "Credit UGX 10,000" when the
     * family has paid more than was billed.
     */
    public static function balance(int|float|string|null $balance): string
    {
        $balance = (int) round((float) $balance);

        return match (true) {
            $balance > 0 => static::format($balance),
            $balance < 0 => 'Credit '.static::format(-$balance),
            default => 'Cleared',
        };
    }

    /**
     * "One million two hundred fifty thousand shillings", for receipts.
     * Returns null where the server lacks PHP's intl extension.
     */
    public static function inWords(int $amount): ?string
    {
        if (! class_exists(NumberFormatter::class)) {
            return null;
        }

        $words = (new NumberFormatter('en', NumberFormatter::SPELLOUT))->format($amount);

        return $words === false ? null : ucfirst(str_replace('-', ' ', $words)).' shillings only';
    }
}
