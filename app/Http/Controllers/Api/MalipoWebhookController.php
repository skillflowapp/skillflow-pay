<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderEvent;
use App\Services\PaymentOrderService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

final class MalipoWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentOrderService $payments,
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->signatureAllowed($request)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $reference = $this->extractReference($payload);
        $status = strtolower((string) ($payload['status'] ?? Arr::get($payload, 'data.status', '')));
        $eventType = (string) ($payload['event'] ?? $payload['type'] ?? 'PAYMENT_UPDATE');
        $eventKey = $this->eventKey($payload, $reference, $status);

        $event = ProviderEvent::firstOrCreate(
            ['event_key' => $eventKey],
            [
                'provider' => 'malipo',
                'event_type' => $eventType,
                'reference' => $reference,
                'status' => 'received',
                'payload' => $payload,
            ],
        );

        if (! $event->wasRecentlyCreated && $event->status === 'processed') {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            if ($reference === '') {
                throw new \RuntimeException('Webhook payload is missing a payment reference.');
            }

            if ($order = $this->payments->findByReference($reference)) {
                if ($this->isFailure($status)) {
                    $this->payments->failPayin($order, $payload);
                } elseif ($this->isSuccess($status)) {
                    $this->payments->settlePayin($order, $payload);
                }
            } elseif ($withdrawal = $this->withdrawals->findByReference($reference)) {
                // Manual approval flow: only act on webhooks for already-approved withdrawals.
                if ($withdrawal->status === 'pending') {
                    throw new \RuntimeException('Withdrawal is pending admin approval. Webhook will be processed after approval.');
                }

                if ($this->isFailure($status)) {
                    $this->withdrawals->reverse($withdrawal, (string) ($payload['message'] ?? 'Payout failed'));
                } elseif ($this->isSuccess($status)) {
                    $this->withdrawals->settle($withdrawal, $payload);
                }
            } else {
                throw new \RuntimeException("No local payment or withdrawal matched reference [{$reference}].");
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'processing_error' => null,
            ]);

            return response()->json(['ok' => true]);
        } catch (Throwable $throwable) {
            $event->update([
                'status' => 'failed',
                'processing_error' => $throwable->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }
    }

    private function signatureAllowed(Request $request): bool
    {
        $secret = (string) config('malipo.webhook_secret');
        if ($secret === '') {
            return true;
        }

        $provided = (string) (
            $request->header('X-Malipo-Signature')
            ?: $request->header('X-Webhook-Signature')
            ?: $request->query('secret', '')
        );

        if ($provided === $secret) {
            return true;
        }

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventKey(array $payload, string $reference, string $status): string
    {
        $id = $payload['id'] ?? $payload['_id'] ?? Arr::get($payload, 'data.id');
        if (is_scalar($id) && (string) $id !== '') {
            return 'malipo:'.(string) $id;
        }

        return 'malipo:'.sha1($reference.'|'.$status.'|'.json_encode($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractReference(array $payload): string
    {
        $value = $payload['reference']
            ?? $payload['paymentReference']
            ?? Arr::get($payload, 'data.reference')
            ?? Arr::get($payload, 'payment.reference')
            ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function isSuccess(string $status): bool
    {
        return in_array($status, ['success', 'successful', 'complete', 'completed', 'paid', 'approved'], true);
    }

    private function isFailure(string $status): bool
    {
        return in_array($status, ['failed', 'failure', 'cancelled', 'canceled', 'expired', 'rejected', 'voided'], true);
    }
}
