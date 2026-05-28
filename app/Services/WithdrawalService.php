<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WithdrawalRequest;
use App\Support\PhoneNumbers;
use Illuminate\Support\Str;
use RuntimeException;

final class WithdrawalService
{
    public function __construct(
        private readonly MalipoPayClient $malipo,
        private readonly WalletLedgerService $wallets,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTeacherWithdrawal(string $ownerUid, array $data): WithdrawalRequest
    {
        return $this->createWithdrawal($ownerUid, 'teacher', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createReferralWithdrawal(string $ownerUid, array $data): WithdrawalRequest
    {
        return $this->createWithdrawal($ownerUid, 'referral', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createWithdrawal(string $ownerUid, string $type, array $data): WithdrawalRequest
    {
        $amount = (int) $data['amount'];
        $feePercent = (float) ($data['platformFeePercent'] ?? 0);
        $fee = (int) round($amount * max(0, min($feePercent, 99.99)) / 100);
        $payoutAmount = max(0, $amount - $fee);
        $currency = (string) ($data['currency'] ?? 'TZS');
        $reference = $this->makeReference('dis');
        $phone = PhoneNumbers::normalizeTanzania((string) $data['accountDetails']);
        $ownerName = (string) ($data['ownerName'] ?? ucfirst($type));

        if ($payoutAmount <= 0) {
            throw new RuntimeException('Withdrawal amount is too low after platform fees.');
        }

        $withdrawal = WithdrawalRequest::create([
            'public_id' => (string) Str::uuid(),
            'reference' => $reference,
            'type' => $type,
            'status' => 'processing',
            'owner_uid' => $ownerUid,
            'owner_name' => $ownerName,
            'requested_amount' => $amount,
            'platform_fee' => $fee,
            'platform_fee_percent' => $feePercent,
            'payout_amount' => $payoutAmount,
            'currency' => $currency,
            'payment_method' => (string) ($data['paymentMethod'] ?? 'Mobile Money'),
            'recipient_phone' => $phone,
            'metadata' => $data['metadata'] ?? [],
            'reserved_at' => now(),
        ]);

        $this->wallets->reserveWithdrawal(
            ownerUid: $ownerUid,
            ownerType: $type,
            amount: $amount,
            currency: $currency,
            sourceId: (string) $withdrawal->id,
            description: "Withdrawal reserved: {$withdrawal->payment_method}",
            ownerName: $ownerName,
            metadata: ['withdrawalPublicId' => $withdrawal->public_id],
        );

        try {
            $response = $this->malipo->disburse($reference, [
                'reference' => $reference,
                'description' => "SkillFlow {$type} withdrawal",
                'amount' => $payoutAmount,
                'phoneNumber' => $phone,
            ]);

            $status = $this->normalizeProviderStatus($this->malipo->extractStatus($response), 'approved');
            $withdrawal->update([
                'status' => $status,
                'provider_reference' => $this->malipo->extractReference($response) ?? $reference,
                'provider_external_reference' => $this->malipo->extractExternalReference($response),
                'processed_at' => now(),
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
        } catch (RuntimeException $exception) {
            $this->reverse($withdrawal, $exception->getMessage());
            throw $exception;
        }

        return $withdrawal->refresh();
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function settle(WithdrawalRequest $withdrawal, array $eventData = []): WithdrawalRequest
    {
        if ($withdrawal->status === 'completed') {
            return $withdrawal;
        }

        $withdrawal->update([
            'status' => 'completed',
            'provider_reference' => $this->malipo->extractReference($eventData) ?? $withdrawal->provider_reference,
            'provider_external_reference' => $this->malipo->extractExternalReference($eventData) ?? $withdrawal->provider_external_reference,
            'processed_at' => $withdrawal->processed_at ?? now(),
            'completed_at' => now(),
            'failure_reason' => null,
        ]);

        return $withdrawal->refresh();
    }

    public function reverse(WithdrawalRequest $withdrawal, ?string $reason = null): WithdrawalRequest
    {
        if ($withdrawal->reversed_at) {
            return $withdrawal;
        }

        $this->wallets->reverseWithdrawal(
            ownerUid: $withdrawal->owner_uid,
            ownerType: $withdrawal->type,
            amount: $withdrawal->requested_amount,
            currency: $withdrawal->currency,
            sourceId: (string) $withdrawal->id,
            ownerName: $withdrawal->owner_name,
        );

        $withdrawal->update([
            'status' => 'rejected',
            'failure_reason' => $reason ?? 'Payout failed',
            'processed_at' => now(),
            'reversed_at' => now(),
        ]);

        return $withdrawal->refresh();
    }

    public function findByReference(string $reference): ?WithdrawalRequest
    {
        return WithdrawalRequest::query()
            ->where('reference', $reference)
            ->orWhere('provider_reference', $reference)
            ->first();
    }

    private function makeReference(string $prefix): string
    {
        return 'sf_'.$prefix.'_'.strtolower(Str::ulid()->toBase32());
    }

    private function normalizeProviderStatus(?string $status, string $fallback): string
    {
        $normalized = strtolower((string) $status);

        return match ($normalized) {
            'success', 'successful', 'complete', 'completed', 'paid', 'approved' => 'completed',
            'failed', 'failure', 'cancelled', 'canceled', 'expired', 'rejected', 'voided' => 'rejected',
            'processing', 'in_progress', 'pending' => 'approved',
            default => $fallback,
        };
    }
}
