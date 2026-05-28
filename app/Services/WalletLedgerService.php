<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WalletLedgerService
{
    public function getOrCreateAccount(
        string $ownerUid,
        string $ownerType,
        string $currency = 'TZS',
        ?string $ownerName = null,
    ): WalletAccount {
        return WalletAccount::firstOrCreate(
            [
                'owner_uid' => $ownerUid,
                'owner_type' => $ownerType,
                'currency' => $currency,
            ],
            [
                'owner_name' => $ownerName,
                'balance' => 0,
                'available_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function credit(
        string $ownerUid,
        string $ownerType,
        int $amount,
        string $currency,
        string $sourceType,
        string $sourceId,
        string $description,
        ?string $ownerName = null,
        array $metadata = [],
    ): void {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($ownerUid, $ownerType, $amount, $currency, $sourceType, $sourceId, $description, $ownerName, $metadata): void {
            $account = $this->getOrCreateAccount($ownerUid, $ownerType, $currency, $ownerName);

            if ($this->entryExists($sourceType, $sourceId, 'credit')) {
                return;
            }

            WalletLedgerEntry::create([
                'wallet_account_id' => $account->id,
                'entry_type' => 'credit',
                'amount' => $amount,
                'currency' => $currency,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $account->increment('balance', $amount);
            $account->increment('available_balance', $amount);
            $account->increment('total_earned', $amount);
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function reserveWithdrawal(
        string $ownerUid,
        string $ownerType,
        int $amount,
        string $currency,
        string $sourceId,
        string $description,
        ?string $ownerName = null,
        array $metadata = [],
    ): void {
        DB::transaction(function () use ($ownerUid, $ownerType, $amount, $currency, $sourceId, $description, $ownerName, $metadata): void {
            $account = $this->getOrCreateAccount($ownerUid, $ownerType, $currency, $ownerName);
            $account->refresh();

            if ($this->entryExists('withdrawal', $sourceId, 'debit')) {
                return;
            }

            if ($account->available_balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Wallet has insufficient available balance.',
                ]);
            }

            WalletLedgerEntry::create([
                'wallet_account_id' => $account->id,
                'entry_type' => 'debit',
                'amount' => $amount,
                'currency' => $currency,
                'source_type' => 'withdrawal',
                'source_id' => $sourceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $account->decrement('balance', $amount);
            $account->decrement('available_balance', $amount);
            $account->increment('total_withdrawn', $amount);
        });
    }

    public function reverseWithdrawal(
        string $ownerUid,
        string $ownerType,
        int $amount,
        string $currency,
        string $sourceId,
        ?string $ownerName = null,
    ): void {
        DB::transaction(function () use ($ownerUid, $ownerType, $amount, $currency, $sourceId, $ownerName): void {
            $account = $this->getOrCreateAccount($ownerUid, $ownerType, $currency, $ownerName);

            if ($this->entryExists('withdrawal_reversal', $sourceId, 'credit')) {
                return;
            }

            WalletLedgerEntry::create([
                'wallet_account_id' => $account->id,
                'entry_type' => 'credit',
                'amount' => $amount,
                'currency' => $currency,
                'source_type' => 'withdrawal_reversal',
                'source_id' => $sourceId,
                'description' => 'Withdrawal reversal',
            ]);

            $account->increment('balance', $amount);
            $account->increment('available_balance', $amount);
            $account->decrement('total_withdrawn', $amount);
        });
    }

    private function entryExists(string $sourceType, string $sourceId, string $entryType): bool
    {
        return WalletLedgerEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('entry_type', $entryType)
            ->exists();
    }
}
