<?php

namespace Tests\Feature\Payments;

use App\Models\WalletAccount;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private string $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uid = 'firebase-test-uid-456';
        $this->token = 'test-token:'.$this->uid;
    }

    public function test_teacher_withdrawal_reserves_funds_and_creates_disbursement(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/disbursement' => Http::response([
                'status' => 'completed',
                'reference' => 'malipo-dis-1',
            ]),
        ]);

        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 50000,
            'available_balance' => 50000,
            'total_earned' => 50000,
            'total_withdrawn' => 0,
        ]);

        $response = $this->postJson('/api/v1/withdrawals/teacher', [
            'amount' => 20000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
            'platformFeePercent' => 5,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');

        $account = WalletAccount::where('owner_uid', $this->uid)->where('owner_type', 'teacher')->first();
        $this->assertSame(30000, $account->balance);
        $this->assertSame(30000, $account->available_balance);
        $this->assertSame(20000, $account->total_withdrawn);
    }

    public function test_teacher_withdrawal_with_insufficient_funds_fails(): void
    {
        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 500,
            'available_balance' => 500,
            'total_earned' => 500,
            'total_withdrawn' => 0,
        ]);

        $response = $this->postJson('/api/v1/withdrawals/teacher', [
            'amount' => 20000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('amount');
    }

    public function test_failed_disbursement_reverses_funds(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/disbursement' => Http::response([
                'status' => 'failed',
                'reference' => 'malipo-dis-2',
                'message' => 'Recipient not found',
            ], 422),
        ]);

        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 50000,
            'available_balance' => 50000,
            'total_earned' => 50000,
            'total_withdrawn' => 0,
        ]);

        $response = $this->postJson('/api/v1/withdrawals/teacher', [
            'amount' => 20000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);

        $account = WalletAccount::where('owner_uid', $this->uid)->where('owner_type', 'teacher')->first();
        $this->assertSame(50000, $account->balance);
        $this->assertSame(50000, $account->available_balance);
        $this->assertSame(0, $account->total_withdrawn);
    }

    public function test_referral_withdrawal_uses_same_reservation_rules(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/disbursement' => Http::response([
                'status' => 'completed',
                'reference' => 'malipo-dis-3',
            ]),
        ]);

        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'referral',
            'currency' => 'TZS',
            'balance' => 10000,
            'available_balance' => 10000,
            'total_earned' => 10000,
            'total_withdrawn' => 0,
        ]);

        $response = $this->postJson('/api/v1/withdrawals/referral', [
            'amount' => 5000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();

        $account = WalletAccount::where('owner_uid', $this->uid)->where('owner_type', 'referral')->first();
        $this->assertSame(5000, $account->balance);
        $this->assertSame(5000, $account->available_balance);
        $this->assertSame(5000, $account->total_withdrawn);
    }

    public function test_withdrawal_webhook_completes_payout(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/disbursement' => Http::response([
                'status' => 'processing',
                'reference' => 'malipo-dis-4',
            ]),
        ]);

        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 50000,
            'available_balance' => 50000,
            'total_earned' => 50000,
            'total_withdrawn' => 0,
        ]);

        $withdrawalResponse = $this->postJson('/api/v1/withdrawals/teacher', [
            'amount' => 20000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $withdrawalResponse->assertOk();
        $withdrawal = WithdrawalRequest::where('owner_uid', $this->uid)->first();
        $this->assertSame('approved', $withdrawal->status);

        // Webhook completes it
        $this->postJson('/api/v1/webhooks/malipo', [
            'reference' => $withdrawal->reference,
            'status' => 'completed',
            'event' => 'PAYOUT_UPDATE',
        ])->assertOk();

        $withdrawal->refresh();
        $this->assertSame('completed', $withdrawal->status);
        $this->assertNotNull($withdrawal->completed_at);
    }

    public function test_withdrawal_webhook_failure_reverses_funds(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/disbursement' => Http::response([
                'status' => 'processing',
                'reference' => 'malipo-dis-5',
            ]),
        ]);

        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 50000,
            'available_balance' => 50000,
            'total_earned' => 50000,
            'total_withdrawn' => 0,
        ]);

        $this->postJson('/api/v1/withdrawals/teacher', [
            'amount' => 20000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
        ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

        $withdrawal = WithdrawalRequest::where('owner_uid', $this->uid)->first();

        $this->postJson('/api/v1/webhooks/malipo', [
            'reference' => $withdrawal->reference,
            'status' => 'failed',
            'event' => 'PAYOUT_UPDATE',
            'message' => 'Network error',
        ])->assertOk();

        $withdrawal->refresh();
        $this->assertSame('rejected', $withdrawal->status);
        $this->assertNotNull($withdrawal->reversed_at);

        $account = WalletAccount::where('owner_uid', $this->uid)->where('owner_type', 'teacher')->first();
        $this->assertSame(50000, $account->balance);
        $this->assertSame(50000, $account->available_balance);
        $this->assertSame(0, $account->total_withdrawn);
    }

    public function test_list_withdrawals_returns_user_requests(): void
    {
        Http::fake([
            'https://api.malipopay.co.tz/api/v1/payment/disbursement' => Http::response([
                'status' => 'completed',
                'reference' => 'malipo-dis-6',
            ]),
        ]);

        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 50000,
            'available_balance' => 50000,
            'total_earned' => 50000,
            'total_withdrawn' => 0,
        ]);

        $this->postJson('/api/v1/withdrawals/teacher', [
            'amount' => 10000,
            'currency' => 'TZS',
            'accountDetails' => '0712345678',
            'paymentMethod' => 'Mobile Money',
        ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

        $response = $this->getJson('/api/v1/withdrawals?type=teacher', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
