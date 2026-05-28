<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'ownerUid' => $this->owner_uid,
            'ownerType' => $this->owner_type,
            'ownerName' => $this->owner_name,
            'currency' => $this->currency,
            'balance' => $this->balance,
            'availableForWithdrawal' => $this->available_balance,
            'totalEarned' => $this->total_earned,
            'totalWithdrawn' => $this->total_withdrawn,
            'ledger' => $this->whenLoaded('ledgerEntries', function (): array {
                return $this->ledgerEntries->map(fn ($entry): array => [
                    'id' => (string) $entry->id,
                    'type' => $entry->entry_type,
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'description' => $entry->description,
                    'referenceId' => $entry->source_id,
                    'referenceType' => $entry->source_type,
                    'createdAt' => $entry->created_at?->toIso8601String(),
                ])->all();
            }),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
