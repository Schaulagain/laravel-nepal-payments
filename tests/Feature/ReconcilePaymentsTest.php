<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconcilePaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_pending_payments_are_completed_from_the_gateway_status(): void
    {
        Http::fake([
            'rc.esewa.com.np/*' => Http::response(['status' => 'COMPLETE', 'total_amount' => 1500.0, 'ref_id' => '0007G36']),
        ]);
        $payment = Payment::factory()->esewa()->create(['amount' => 150000, 'created_at' => now()->subMinutes(30)]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('0007G36', $payment->transaction_reference);
        $this->assertNotNull($payment->invoice);
    }

    public function test_recent_and_finished_payments_are_left_alone(): void
    {
        Http::fake();
        Payment::factory()->esewa()->create(['created_at' => now()->subMinutes(2)]);
        Payment::factory()->esewa()->completed()->create(['created_at' => now()->subHour()]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_gateway_outages_leave_payments_pending_for_the_next_run(): void
    {
        Http::fake(['rc.esewa.com.np/*' => Http::response('Service Unavailable', 503)]);
        $payment = Payment::factory()->esewa()->create(['created_at' => now()->subMinutes(30)]);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }
}
