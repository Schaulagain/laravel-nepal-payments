<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * Whether the payment can no longer change state.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
