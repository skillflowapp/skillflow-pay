<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'reference' => $this->provider_reference ?: $this->reference,
            'skillflowReference' => $this->reference,
            'type' => $this->type,
            'status' => $this->status,
            'ownerUid' => $this->owner_uid,
            'ownerName' => $this->owner_name,
            'amount' => $this->requested_amount,
            'requestedAmount' => $this->requested_amount,
            'platformFee' => $this->platform_fee,
            'platformFeePercent' => (float) $this->platform_fee_percent,
            'payoutAmount' => $this->payout_amount,
            'currency' => $this->currency,
            'paymentMethod' => $this->payment_method,
            'accountDetails' => $this->recipient_phone,
            'transactionId' => $this->provider_reference,
            'externalReference' => $this->provider_external_reference,
            'rejectionReason' => $this->failure_reason,
            'requestedAt' => $this->created_at?->toIso8601String(),
            'processedAt' => $this->processed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'payoutRestoredAt' => $this->reversed_at?->toIso8601String(),
        ];
    }
}
