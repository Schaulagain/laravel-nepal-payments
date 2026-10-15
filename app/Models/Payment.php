<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * One attempt to pay for an order through a gateway. Amounts are in paisa.
 */
#[Fillable([
    'order_id', 'gateway', 'amount', 'status', 'external_id',
    'transaction_reference', 'failure_reason', 'gateway_response', 'completed_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'gateway_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending);
    }

    /**
     * The amount in rupees, formatted the way gateways expect it (e.g. "1500.00").
     */
    public function amountInRupees(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }
}
