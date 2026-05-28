<?php

namespace Tests\Feature\Payments;

use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private string $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uid = 'firebase-test-uid-wallet';
        $this->token = 'test-token:'.$this->uid;
    }

    public function test_get_wallet_creates_account_if_missing(): void
    {
        $response = $this->getJson('/api/v1/wallet?type=teacher', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.ownerUid', $this->uid);
        $response->assertJsonPath('data.ownerType', 'teacher');
        $response->assertJsonPath('data.balance', 0);

        $this->assertDatabaseHas('wallet_accounts', [
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
        ]);
    }

    public function test_get_wallet_returns_balance_and_ledger(): void
    {
        $account = WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 25000,
            'available_balance' => 25000,
            'total_earned' => 25000,
            'total_withdrawn' => 0,
        ]);

        WalletLedgerEntry::create([
            'wallet_account_id' => $account->id,
            'entry_type' => 'credit',
            'amount' => 25000,
            'currency' => 'TZS',
            'source_type' => 'payment_order',
            'source_id' => '123',
            'description' => 'Purchase: content',
        ]);

        $response = $this->getJson('/api/v1/wallet?type=teacher', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.balance', 25000);
        $response->assertJsonPath('data.availableForWithdrawal', 25000);
        $response->assertJsonPath('data.totalEarned', 25000);
        $response->assertJsonCount(1, 'data.ledger');
    }

    public function test_get_referral_wallet_returns_referral_account(): void
    {
        WalletAccount::create([
            'owner_uid' => $this->uid,
            'owner_type' => 'referral',
            'currency' => 'TZS',
            'balance' => 10000,
            'available_balance' => 10000,
            'total_earned' => 10000,
            'total_withdrawn' => 0,
        ]);

        $response = $this->getJson('/api/v1/wallet?type=referral', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.ownerType', 'referral');
        $response->assertJsonPath('data.balance', 10000);
    }
}
