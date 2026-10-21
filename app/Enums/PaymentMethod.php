<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MtnMobileMoney = 'mtn_momo';
    case AirtelMoney = 'airtel_money';
    case SchoolPay = 'schoolpay';
    case Cheque = 'cheque';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank deposit',
            self::MtnMobileMoney => 'MTN Mobile Money',
            self::AirtelMoney => 'Airtel Money',
            self::SchoolPay => 'SchoolPay',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }

    /**
     * Everything except cash should have a slip or transaction number.
     */
    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }
}
