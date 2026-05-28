<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentOrderTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private string $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uid = 'firebase-test-uid-123';
        $this->token = 'test-token:'.$this->uid;
    }

    public function test_authenticated_request_with_valid_firebase_token_succeeds(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-ref-1',
                'data' => ['link' => 'https://pay.malipo.co.tz/123'],
            ]),
        ]);

        $response = $this->postJson('/api/v1/payments/content', [
            'contentId' => 'content-1',
            'phoneNumber' => '0712345678',
            'amount' => 5000,
            'currency' => 'TZS',
            'payeeUid' => 'teacher-1',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_missing_firebase_token_fails(): void
    {
        $response = $this->postJson('/api/v1/payments/content', [
            'contentId' => 'content-1',
            'phoneNumber' => '0712345678',
            'amount' => 5000,
            'currency' => 'TZS',
            'payeeUid' => 'teacher-1',
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('error', 'Unauthenticated');
    }

    public function test_content_payment_creates_pending_order(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-ref-2',
                'data' => ['link' => 'https://pay.malipo.co.tz/456'],
            ]),
        ]);

        $response = $this->postJson('/api/v1/payments/content', [
            'contentId' => 'content-2',
            'phoneNumber' => '0712345678',
            'amount' => 5000,
            'currency' => 'TZS',
            'payeeUid' => 'teacher-1',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_orders', [
            'payer_uid' => $this->uid,
            'type' => 'content',
            'status' => 'pending',
        ]);
    }

    public function test_content_payment_reuses_existing_pending_order(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-ref-3',
            ]),
        ]);

        // First request
        $this->postJson('/api/v1/payments/content', [
            'contentId' => 'content-3',
            'phoneNumber' => '0712345678',
            'amount' => 5000,
            'currency' => 'TZS',
            'payeeUid' => 'teacher-1',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $countBefore = PaymentOrder::where('payer_uid', $this->uid)->where('type', 'content')->count();

        // Second request with same contentId
        $response = $this->postJson('/api/v1/payments/content', [
            'contentId' => 'content-3',
            'phoneNumber' => '0712345678',
            'amount' => 5000,
            'currency' => 'TZS',
            'payeeUid' => 'teacher-1',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $this->assertSame($countBefore, PaymentOrder::where('payer_uid', $this->uid)->where('type', 'content')->count());
    }

    public function test_ai_subscription_payment_creates_pending_order(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-ref-4',
            ]),
        ]);

        $response = $this->postJson('/api/v1/payments/ai-subscription', [
            'planId' => 'plan-pro',
            'phoneNumber' => '0712345678',
            'amount' => 10000,
            'currency' => 'TZS',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_orders', [
            'payer_uid' => $this->uid,
            'type' => 'ai_subscription',
            'status' => 'pending',
        ]);
    }

    public function test_ai_credits_payment_creates_pending_order(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-ref-5',
            ]),
        ]);

        $response = $this->postJson('/api/v1/payments/ai-credits', [
            'packId' => 'pack-medium',
            'phoneNumber' => '0712345678',
            'amount' => 4500,
            'currency' => 'TZS',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_orders', [
            'payer_uid' => $this->uid,
            'type' => 'ai_credits',
            'status' => 'pending',
        ]);
    }

    public function test_show_payment_requires_ownership(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-ref-6',
            ]),
        ]);

        $order = PaymentOrder::create([
            'public_id' => 'pay-1',
            'reference' => 'sf_col_abc123',
            'idempotency_key' => 'idem-1',
            'type' => 'content',
            'status' => 'pending',
            'payer_uid' => 'other-user',
            'amount' => 5000,
            'currency' => 'TZS',
        ]);

        $response = $this->getJson('/api/v1/payments/'.$order->public_id, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertForbidden();
    }

    public function test_access_endpoint_returns_access_status(): void
    {
        PaymentOrder::create([
            'public_id' => 'pay-2',
            'reference' => 'sf_col_def456',
            'idempotency_key' => 'idem-2',
            'type' => 'content',
            'status' => 'completed',
            'payer_uid' => $this->uid,
            'amount' => 5000,
            'currency' => 'TZS',
            'metadata' => ['contentId' => 'content-access-1'],
        ]);

        $response = $this->getJson('/api/v1/payments/access?contentId=content-access-1', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.hasAccess', true);
    }

    public function test_malipo_collection_success_stores_provider_reference(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/collection' => Http::response([
                'status' => 'pending',
                'reference' => 'malipo-external-ref',
                'external_reference' => 'ext-123',
                'data' => ['link' => 'https://pay.malipo.co.tz/789'],
            ]),
        ]);

        $response = $this->postJson('/api/v1/payments/content', [
            'contentId' => 'content-ref',
            'phoneNumber' => '0712345678',
            'amount' => 5000,
            'currency' => 'TZS',
            'payeeUid' => 'teacher-1',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_orders', [
            'payer_uid' => $this->uid,
            'provider_reference' => 'malipo-external-ref',
            'provider_external_reference' => 'ext-123',
        ]);
    }
}
