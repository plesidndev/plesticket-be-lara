<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneWebhookDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(WebhookDeliveryStatus $status, int $daysAgo): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'id' => (string) Str::uuid(), 'provider' => PaymentProvider::Xendit, 'event_type' => 'payment.succeeded',
            'reference_id' => 'REF-'.Str::random(6), 'status' => $status, 'payload' => ['ok' => true],
        ]);

        // created_at is not fillable, so it has to be backdated after the fact.
        $delivery->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $delivery;
    }

    public function test_it_removes_settled_deliveries_past_the_window(): void
    {
        $this->delivery(WebhookDeliveryStatus::Applied, 120);
        $this->delivery(WebhookDeliveryStatus::Ignored, 100);
        $this->delivery(WebhookDeliveryStatus::Skipped, 91);

        $this->artisan('webhooks:prune')->assertSuccessful();

        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_it_keeps_settled_deliveries_inside_the_window(): void
    {
        $this->delivery(WebhookDeliveryStatus::Applied, 10);

        $this->artisan('webhooks:prune')->assertSuccessful();

        $this->assertSame(1, WebhookDelivery::count());
    }

    public function test_it_never_removes_the_queue_a_human_still_has_to_work(): void
    {
        // Failed and unmatched are the Operations queue; age must not clear them.
        $this->delivery(WebhookDeliveryStatus::Failed, 400);
        $this->delivery(WebhookDeliveryStatus::Unmatched, 400);

        $this->artisan('webhooks:prune')->assertSuccessful();

        $this->assertSame(2, WebhookDelivery::count());
    }

    public function test_the_window_is_configurable(): void
    {
        $this->delivery(WebhookDeliveryStatus::Applied, 10);

        $this->artisan('webhooks:prune', ['--days' => 7])->assertSuccessful();

        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_it_deletes_in_chunks_until_nothing_is_left(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->delivery(WebhookDeliveryStatus::Applied, 120);
        }

        $this->artisan('webhooks:prune', ['--chunk' => 2])->assertSuccessful();

        $this->assertSame(0, WebhookDelivery::count());
    }
}
