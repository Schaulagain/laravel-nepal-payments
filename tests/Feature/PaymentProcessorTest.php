<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\GatewayResult;
use App\Payments\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_result_without_an_amount_leaves_the_payment_pending(): void
    {
        $payment = Payment::factory()->esewa()->create(['amount' => 150000]);

        app(PaymentProcessor::class)->apply($payment, new GatewayResult(PaymentStatus::Completed, amount: null));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->invoice);
    }
}
