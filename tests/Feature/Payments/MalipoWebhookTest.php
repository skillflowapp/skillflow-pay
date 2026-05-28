<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentOrder;
use App\Models\ProviderEvent;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MalipoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_webhook_without_secret_allows_empty_config(): void
    {
        $order = PaymentOrder::create([
            'public_id' => 'pay-webhook-1',
            'reference' => 'sf_col_webhook1',
            'idempotency_key' => 'idem-webhook-1',
            'type' => 'content',
            'status' => 'pending',
            'payer_uid' => 'user-1',
            'amount' => 5000,
            'currency' => 'TZS',
            'payee_uid' => 'teacher-1',
            'payee_amount' => 4500,
        ]);

        WalletAccount::create([
            'owner_uid' => 'teacher-1',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 0,
            'available_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $response = $this->postJson('/api/v1/webhooks/malipo', [
            'reference' => 'sf_col_webhook1',
            'status' => 'completed',
            'event' => 'PAYMENT_UPDATE',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);

        $this->assertDatabaseHas('wallet_accounts', [
            'owner_uid' => 'teacher-1',
            'balance' => 4500,
        ]);
    }

    public function test_webhook_completed_event_credits_wallet_once_even_if_delivered_twice(): void
    {
        $order = PaymentOrder::create([
            'public_id' => 'pay-webhook-2',
            'reference' => 'sf_col_webhook2',
            'idempotency_key' => 'idem-webhook-2',
            'type' => 'content',
            'status' => 'pending',
            'payer_uid' => 'user-1',
            'amount' => 5000,
            'currency' => 'TZS',
            'payee_uid' => 'teacher-2',
            'payee_amount' => 4500,
        ]);

        WalletAccount::create([
            'owner_uid' => 'teacher-2',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 0,
            'available_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $payload = [
            'reference' => 'sf_col_webhook2',
            'status' => 'completed',
            'event' => 'PAYMENT_UPDATE',
        ];

        $this->postJson('/api/v1/webhooks/malipo', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/malipo', $payload)->assertOk();

        $account = WalletAccount::where('owner_uid', 'teacher-2')->first();
        $this->assertSame(4500, $account->balance);
        $this->assertSame(4500, $account->total_earned);
        $this->assertSame(1, WalletAccount::where('owner_uid', 'teacher-2')->first()->ledgerEntries()->count());
    }

    public function test_webhook_failed_event_marks_order_failed_without_crediting_wallet(): void
    {
        $order = PaymentOrder::create([
            'public_id' => 'pay-webhook-3',
            'reference' => 'sf_col_webhook3',
            'idempotency_key' => 'idem-webhook-3',
            'type' => 'content',
            'status' => 'pending',
            'payer_uid' => 'user-1',
            'amount' => 5000,
            'currency' => 'TZS',
            'payee_uid' => 'teacher-3',
            'payee_amount' => 4500,
        ]);

        $response = $this->postJson('/api/v1/webhooks/malipo', [
            'reference' => 'sf_col_webhook3',
            'status' => 'failed',
            'event' => 'PAYMENT_UPDATE',
            'message' => 'Insufficient funds',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertSame('failed', $order->status);
        $this->assertSame('Insufficient funds', $order->failure_reason);

        $this->assertDatabaseMissing('wallet_accounts', [
            'owner_uid' => 'teacher-3',
        ]);
    }

    public function test_webhook_dedupes_by_event_key(): void
    {
        $order = PaymentOrder::create([
            'public_id' => 'pay-webhook-4',
            'reference' => 'sf_col_webhook4',
            'idempotency_key' => 'idem-webhook-4',
            'type' => 'content',
            'status' => 'pending',
            'payer_uid' => 'user-1',
            'amount' => 5000,
            'currency' => 'TZS',
            'payee_uid' => 'teacher-4',
            'payee_amount' => 4500,
        ]);

        WalletAccount::create([
            'owner_uid' => 'teacher-4',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 0,
            'available_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $payload = [
            'id' => 'event-unique-123',
            'reference' => 'sf_col_webhook4',
            'status' => 'completed',
            'event' => 'PAYMENT_UPDATE',
        ];

        $this->postJson('/api/v1/webhooks/malipo', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/malipo', $payload)->assertOk();

        $this->assertSame(1, ProviderEvent::where('event_key', 'malipo:event-unique-123')->count());

        $account = WalletAccount::where('owner_uid', 'teacher-4')->first();
        $this->assertSame(4500, $account->balance);
        $this->assertSame(1, $account->ledgerEntries()->count());
    }

    public function test_webhook_missing_reference_returns_error(): void
    {
        $response = $this->postJson('/api/v1/webhooks/malipo', [
            'status' => 'completed',
            'event' => 'PAYMENT_UPDATE',
        ]);

        $response->assertServerError();
        $this->assertDatabaseHas('provider_events', [
            'status' => 'failed',
        ]);
    }

    public function test_webhook_with_invalid_signature_returns_401(): void
    {
        config(['malipo.webhook_secret' => 'my-secret']);

        $response = $this->postJson('/api/v1/webhooks/malipo', [
            'reference' => 'sf_col_webhook5',
            'status' => 'completed',
        ], ['X-Malipo-Signature' => 'wrong-secret']);

        $response->assertUnauthorized();
        $response->assertJsonPath('message', 'Invalid webhook signature.');
    }
}
