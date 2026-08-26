<?php

namespace App\Enums;

/**
 * How the buyer pays — used to group methods in the picker UI.
 */
enum PaymentType: string
{
    case Qris           = 'qris';
    case VirtualAccount = 'virtual_account';
    case BankTransfer   = 'bank_transfer';
    case EWallet        = 'ewallet';

    public function label(): string
    {
        return match($this) {
            self::Qris           => 'QRIS',
            self::VirtualAccount => 'Virtual Account',
            self::BankTransfer   => 'Bank Transfer',
            self::EWallet        => 'E-Wallet',
        };
    }
}
