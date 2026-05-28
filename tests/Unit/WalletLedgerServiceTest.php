<?php

namespace Tests\Unit;

use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WalletLedgerService;
    }

    public function test_credit_creates_account_and_entry(): void
    {
        $this->service->credit(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceType: 'payment_order',
            sourceId: '123',
            description: 'Test credit',
        );

        $account = WalletAccount::where('owner_uid', 'user-1')->first();
        $this->assertNotNull($account);
        $this->assertSame(5000, $account->balance);
        $this->assertSame(5000, $account->available_balance);
        $this->assertSame(5000, $account->total_earned);
        $this->assertSame(1, $account->ledgerEntries()->count());
    }

    public function test_credit_is_idempotent(): void
    {
        $this->service->credit(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceType: 'payment_order',
            sourceId: 'same-id',
            description: 'Test credit',
        );

        $this->service->credit(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceType: 'payment_order',
            sourceId: 'same-id',
            description: 'Duplicate credit',
        );

        $account = WalletAccount::where('owner_uid', 'user-1')->first();
        $this->assertSame(5000, $account->balance);
        $this->assertSame(1, $account->ledgerEntries()->count());
    }

    public function test_credit_with_zero_amount_does_nothing(): void
    {
        $this->service->credit(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 0,
            currency: 'TZS',
            sourceType: 'payment_order',
            sourceId: 'zero',
            description: 'Zero credit',
        );

        $this->assertDatabaseMissing('wallet_accounts', [
            'owner_uid' => 'user-1',
        ]);
    }

    public function test_reserve_withdrawal_decreases_balances(): void
    {
        WalletAccount::create([
            'owner_uid' => 'user-1',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 10000,
            'available_balance' => 10000,
            'total_earned' => 10000,
            'total_withdrawn' => 0,
        ]);

        $this->service->reserveWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'withdrawal-1',
            description: 'Test withdrawal',
        );

        $account = WalletAccount::where('owner_uid', 'user-1')->first();
        $this->assertSame(5000, $account->balance);
        $this->assertSame(5000, $account->available_balance);
        $this->assertSame(5000, $account->total_withdrawn);
        $this->assertSame(1, $account->ledgerEntries()->count());
    }

    public function test_reserve_withdrawal_fails_on_insufficient_balance(): void
    {
        WalletAccount::create([
            'owner_uid' => 'user-1',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 1000,
            'available_balance' => 1000,
            'total_earned' => 1000,
            'total_withdrawn' => 0,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->reserveWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'withdrawal-1',
            description: 'Test withdrawal',
        );
    }

    public function test_reserve_withdrawal_is_idempotent(): void
    {
        WalletAccount::create([
            'owner_uid' => 'user-1',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 10000,
            'available_balance' => 10000,
            'total_earned' => 10000,
            'total_withdrawn' => 0,
        ]);

        $this->service->reserveWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'same-withdrawal',
            description: 'First',
        );

        $this->service->reserveWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'same-withdrawal',
            description: 'Duplicate',
        );

        $account = WalletAccount::where('owner_uid', 'user-1')->first();
        $this->assertSame(5000, $account->balance);
        $this->assertSame(5000, $account->available_balance);
        $this->assertSame(5000, $account->total_withdrawn);
        $this->assertSame(1, $account->ledgerEntries()->count());
    }

    public function test_reverse_withdrawal_restores_funds(): void
    {
        WalletAccount::create([
            'owner_uid' => 'user-1',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 5000,
            'available_balance' => 5000,
            'total_earned' => 10000,
            'total_withdrawn' => 5000,
        ]);

        WalletLedgerEntry::create([
            'wallet_account_id' => WalletAccount::where('owner_uid', 'user-1')->first()->id,
            'entry_type' => 'debit',
            'amount' => 5000,
            'currency' => 'TZS',
            'source_type' => 'withdrawal',
            'source_id' => 'withdrawal-1',
            'description' => 'Reserved',
        ]);

        $this->service->reverseWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'withdrawal-1',
        );

        $account = WalletAccount::where('owner_uid', 'user-1')->first();
        $this->assertSame(10000, $account->balance);
        $this->assertSame(10000, $account->available_balance);
        $this->assertSame(0, $account->total_withdrawn);
        $this->assertSame(2, $account->ledgerEntries()->count());
    }

    public function test_reverse_withdrawal_is_idempotent(): void
    {
        WalletAccount::create([
            'owner_uid' => 'user-1',
            'owner_type' => 'teacher',
            'currency' => 'TZS',
            'balance' => 5000,
            'available_balance' => 5000,
            'total_earned' => 10000,
            'total_withdrawn' => 5000,
        ]);

        WalletLedgerEntry::create([
            'wallet_account_id' => WalletAccount::where('owner_uid', 'user-1')->first()->id,
            'entry_type' => 'debit',
            'amount' => 5000,
            'currency' => 'TZS',
            'source_type' => 'withdrawal',
            'source_id' => 'withdrawal-1',
            'description' => 'Reserved',
        ]);

        $this->service->reverseWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'withdrawal-1',
        );

        $this->service->reverseWithdrawal(
            ownerUid: 'user-1',
            ownerType: 'teacher',
            amount: 5000,
            currency: 'TZS',
            sourceId: 'withdrawal-1',
        );

        $account = WalletAccount::where('owner_uid', 'user-1')->first();
        $this->assertSame(10000, $account->balance);
        $this->assertSame(2, $account->ledgerEntries()->count());
    }
}
