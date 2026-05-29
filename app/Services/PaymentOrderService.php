<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentOrder;
use App\Support\PhoneNumbers;
use Illuminate\Support\Str;
use RuntimeException;

final class PaymentOrderService
{
    public function __construct(
        private readonly MalipoPayClient $malipo,
        private readonly WalletLedgerService $wallets,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createContentOrder(string $payerUid, array $data): PaymentOrder
    {
        $contentId = (string) ($data['contentId'] ?? '');
        $playlistId = (string) ($data['playlistId'] ?? '');
        $itemId = $contentId !== '' ? $contentId : $playlistId;
        $type = $playlistId !== '' ? 'playlist' : 'content';

        $existing = $this->findPendingOrder($payerUid, $type, $itemId);
        if ($existing) {
            return $existing;
        }

        $amount = (int) $data['amount'];
        $platformFee = (int) round($amount * ((float) ($data['platformFeePercent'] ?? 10) / 100));
        $payeeAmount = max(0, $amount - $platformFee);

        return $this->createPayinOrder(
            payerUid: $payerUid,
            type: $type,
            amount: $amount,
            currency: (string) ($data['currency'] ?? 'TZS'),
            phoneNumber: (string) $data['phoneNumber'],
            description: (string) ($data['description'] ?? 'SkillFlow content purchase'),
            payeeUid: (string) ($data['payeeUid'] ?? ''),
            payeeAmount: $payeeAmount,
            platformFee: $platformFee,
            metadata: [
                'contentId' => $contentId ?: null,
                'playlistId' => $playlistId ?: null,
                'studentName' => $data['studentName'] ?? null,
                'payeeName' => $data['payeeName'] ?? null,
                'title' => $data['title'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAiSubscriptionOrder(string $payerUid, array $data): PaymentOrder
    {
        $planId = (string) $data['planId'];
        $existing = $this->findPendingOrder($payerUid, 'ai_subscription', $planId);
        if ($existing) {
            return $existing;
        }

        $amount = (int) $data['amount'];

        return $this->createPayinOrder(
            payerUid: $payerUid,
            type: 'ai_subscription',
            amount: $amount,
            currency: (string) ($data['currency'] ?? 'TZS'),
            phoneNumber: (string) $data['phoneNumber'],
            description: (string) ($data['description'] ?? 'SkillFlow AI subscription'),
            payeeUid: 'platform',
            payeeAmount: 0,
            platformFee: $amount,
            metadata: [
                'planId' => $planId,
                'planName' => $data['planName'] ?? null,
                'monthlyCredits' => $data['monthlyCredits'] ?? null,
                'creditType' => $data['creditType'] ?? null,
                'features' => $data['features'] ?? [],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAiCreditOrder(string $payerUid, array $data): PaymentOrder
    {
        $packId = (string) $data['packId'];
        $existing = $this->findPendingOrder($payerUid, 'ai_credits', $packId);
        if ($existing) {
            return $existing;
        }

        $amount = (int) $data['amount'];

        return $this->createPayinOrder(
            payerUid: $payerUid,
            type: 'ai_credits',
            amount: $amount,
            currency: (string) ($data['currency'] ?? 'TZS'),
            phoneNumber: (string) $data['phoneNumber'],
            description: (string) ($data['description'] ?? 'SkillFlow AI credits'),
            payeeUid: 'platform',
            payeeAmount: 0,
            platformFee: $amount,
            metadata: [
                'packId' => $packId,
                'packName' => $data['packName'] ?? null,
                'credits' => $data['credits'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createPayinOrder(
        string $payerUid,
        string $type,
        int $amount,
        string $currency,
        string $phoneNumber,
        string $description,
        ?string $payeeUid,
        int $payeeAmount,
        int $platformFee,
        array $metadata,
    ): PaymentOrder {
        $reference = $this->makeReference('col');
        $normalizedPhone = PhoneNumbers::normalizeTanzania($phoneNumber);

        $order = PaymentOrder::create([
            'public_id' => (string) Str::uuid(),
            'reference' => $reference,
            'idempotency_key' => (string) Str::uuid(),
            'type' => $type,
            'status' => 'pending',
            'payer_uid' => $payerUid,
            'payee_uid' => $payeeUid !== '' ? $payeeUid : null,
            'amount' => $amount,
            'currency' => $currency,
            'platform_fee' => $platformFee,
            'payee_amount' => $payeeAmount,
            'metadata' => $metadata + ['phoneNumber' => $normalizedPhone],
        ]);

        try {
            $response = $this->malipo->collect($reference, [
                'reference' => $reference,
                'description' => $description,
                'amount' => $amount,
                'phoneNumber' => $normalizedPhone,
            ]);

            $order->update([
                'provider_reference' => $this->malipo->extractReference($response) ?? $reference,
                'provider_external_reference' => $this->malipo->extractExternalReference($response),
                'provider_link' => $this->malipo->extractLink($response),
                'status' => $this->normalizeProviderStatus($this->malipo->extractStatus($response), 'pending'),
            ]);
        } catch (RuntimeException $exception) {
            $order->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            throw $exception;
        }

        return $order->refresh();
    }

    public function refreshFromProvider(PaymentOrder $order): PaymentOrder
    {
        if (! in_array($order->status, ['pending', 'processing'], true)) {
            return $order;
        }

        $reference = $order->provider_reference ?: $order->reference;
        $response = $this->malipo->verify($reference);

        $status = $this->normalizeProviderStatus($this->malipo->extractStatus($response), $order->status);
        if ($status === 'completed') {
            return $this->settlePayin($order, $response);
        }

        if ($status === 'failed') {
            $order->update([
                'status' => 'failed',
                'failure_reason' => $this->extractFailureReason($response),
                'failed_at' => now(),
            ]);
        }

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function settlePayin(PaymentOrder $order, array $eventData = []): PaymentOrder
    {
        if ($order->settled_at !== null) {
            return $order;
        }

        if ($order->status === 'canceled') {
            return $order;
        }

        $order->update([
            'status' => 'completed',
            'provider_reference' => $this->malipo->extractReference($eventData) ?? $order->provider_reference,
            'provider_external_reference' => $this->malipo->extractExternalReference($eventData) ?? $order->provider_external_reference,
            'failure_reason' => null,
            'access_granted_at' => $order->access_granted_at ?? now(),
            'settled_at' => $order->settled_at ?? now(),
        ]);

        if ($order->payee_uid && $order->payee_amount > 0) {
            $this->wallets->credit(
                ownerUid: $order->payee_uid,
                ownerType: 'teacher',
                amount: $order->payee_amount,
                currency: $order->currency,
                sourceType: 'payment_order',
                sourceId: (string) $order->id,
                description: "Purchase: {$order->type}",
                ownerName: (string) ($order->metadata['payeeName'] ?? 'Teacher'),
                metadata: ['paymentOrderPublicId' => $order->public_id],
            );
        }

        if ($order->platform_fee > 0) {
            $this->wallets->credit(
                ownerUid: 'platform',
                ownerType: 'platform',
                amount: $order->platform_fee,
                currency: $order->currency,
                sourceType: 'platform_fee',
                sourceId: (string) $order->id,
                description: "Platform fee: {$order->type}",
                ownerName: 'SkillFlow',
            );
        }

        return $order->refresh();
    }

    public function cancelPayin(PaymentOrder $order, string $reason = 'Canceled by student'): PaymentOrder
    {
        if ($order->status === 'canceled') {
            return $order;
        }

        if ($order->settled_at !== null || $order->status === 'completed') {
            throw new RuntimeException('Completed payments cannot be canceled.');
        }

        if (! in_array($order->status, ['pending', 'processing'], true)) {
            throw new RuntimeException('This payment can no longer be canceled.');
        }

        $order->update([
            'status' => 'canceled',
            'failure_reason' => $reason !== '' ? $reason : 'Canceled by student',
            'failed_at' => now(),
        ]);

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function failPayin(PaymentOrder $order, array $eventData = []): PaymentOrder
    {
        if (in_array($order->status, ['completed', 'canceled'], true)) {
            return $order;
        }

        $order->update([
            'status' => 'failed',
            'failure_reason' => $this->extractFailureReason($eventData),
            'failed_at' => now(),
        ]);

        return $order->refresh();
    }

    public function findByReference(string $reference): ?PaymentOrder
    {
        return PaymentOrder::query()
            ->where('reference', $reference)
            ->orWhere('provider_reference', $reference)
            ->first();
    }

    private function findPendingOrder(string $payerUid, string $type, string $itemId): ?PaymentOrder
    {
        return PaymentOrder::query()
            ->where('payer_uid', $payerUid)
            ->where('type', $type)
            ->where('status', 'pending')
            ->where(function ($query) use ($itemId): void {
                $query->where('metadata->contentId', $itemId)
                    ->orWhere('metadata->playlistId', $itemId)
                    ->orWhere('metadata->planId', $itemId)
                    ->orWhere('metadata->packId', $itemId);
            })
            ->latest()
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
            'failed', 'failure', 'cancelled', 'canceled', 'expired', 'rejected', 'voided' => 'failed',
            'processing', 'in_progress' => 'processing',
            default => $fallback,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractFailureReason(array $data): string
    {
        $message = $data['message'] ?? $data['failure_reason'] ?? $data['status'] ?? 'Payment failed';

        return is_scalar($message) ? (string) $message : 'Payment failed';
    }
}
