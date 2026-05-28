<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'paymentId' => $this->public_id,
            'reference' => $this->provider_reference ?: $this->reference,
            'skillflowReference' => $this->reference,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'providerLink' => $this->provider_link,
            'failureReason' => $this->failure_reason,
            'metadata' => $this->metadata,
            'createdAt' => $this->created_at?->toIso8601String(),
            'settledAt' => $this->settled_at?->toIso8601String(),
        ];
    }
}
