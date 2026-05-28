<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function index(Request $request)
    {
        $type = (string) $request->query('type', 'teacher');
        if (! in_array($type, ['teacher', 'referral'], true)) {
            $type = 'teacher';
        }

        $requests = WithdrawalRequest::query()
            ->where('owner_uid', $this->uid($request))
            ->where('type', $type)
            ->latest()
            ->limit(100)
            ->get();

        return WithdrawalResource::collection($requests);
    }

    public function teacher(Request $request): JsonResource
    {
        $data = $this->validatedWithdrawal($request);

        return new WithdrawalResource(
            $this->withdrawals->createTeacherWithdrawal($this->uid($request), $data)
        );
    }

    public function referral(Request $request): JsonResource
    {
        $data = $this->validatedWithdrawal($request);

        return new WithdrawalResource(
            $this->withdrawals->createReferralWithdrawal($this->uid($request), $data)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedWithdrawal(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
            'paymentMethod' => ['nullable', 'string'],
            'accountDetails' => ['required', 'string'],
            'ownerName' => ['nullable', 'string'],
            'platformFeePercent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    private function uid(Request $request): string
    {
        return (string) $request->attributes->get('firebase_uid');
    }
}
