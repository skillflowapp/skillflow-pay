<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Services\WalletLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WalletController extends Controller
{
    public function __construct(
        private readonly WalletLedgerService $wallets,
    ) {}

    public function __invoke(Request $request): JsonResource
    {
        $ownerType = (string) $request->query('type', 'teacher');
        if (! in_array($ownerType, ['teacher', 'referral'], true)) {
            $ownerType = 'teacher';
        }

        $wallet = $this->wallets
            ->getOrCreateAccount((string) $request->attributes->get('firebase_uid'), $ownerType)
            ->load(['ledgerEntries' => fn ($query) => $query->latest()->limit(25)]);

        return new WalletResource($wallet);
    }
}
