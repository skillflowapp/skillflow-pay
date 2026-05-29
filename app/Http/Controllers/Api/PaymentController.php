<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentOrderResource;
use App\Models\PaymentOrder;
use App\Services\PaymentOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentOrderService $payments,
    ) {}

    public function index(Request $request)
    {
        $role = (string) $request->query('role', 'payer');
        $status = (string) $request->query('status', '');
        $uid = $this->uid($request);

        $orders = PaymentOrder::query()
            ->when(
                $role === 'payee',
                fn ($query) => $query->where('payee_uid', $uid),
                fn ($query) => $query->where('payer_uid', $uid),
            )
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->limit(100)
            ->get();

        return PaymentOrderResource::collection($orders);
    }

    public function content(Request $request): JsonResource
    {
        $data = $request->validate([
            'contentId' => ['nullable', 'string', 'required_without:playlistId'],
            'playlistId' => ['nullable', 'string', 'required_without:contentId'],
            'phoneNumber' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
            'payeeUid' => ['required', 'string'],
            'payeeName' => ['nullable', 'string'],
            'studentName' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'platformFeePercent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
        ]);

        return new PaymentOrderResource(
            $this->payments->createContentOrder($this->uid($request), $data)
        );
    }

    public function aiSubscription(Request $request): JsonResource
    {
        $data = $request->validate([
            'planId' => ['required', 'string'],
            'phoneNumber' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
            'planName' => ['nullable', 'string'],
            'monthlyCredits' => ['nullable', 'integer'],
            'creditType' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
        ]);

        return new PaymentOrderResource(
            $this->payments->createAiSubscriptionOrder($this->uid($request), $data)
        );
    }

    public function aiCredits(Request $request): JsonResource
    {
        $data = $request->validate([
            'packId' => ['required', 'string'],
            'phoneNumber' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
            'packName' => ['nullable', 'string'],
            'credits' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ]);

        return new PaymentOrderResource(
            $this->payments->createAiCreditOrder($this->uid($request), $data)
        );
    }

    public function show(Request $request, PaymentOrder $paymentOrder): JsonResource
    {
        abort_unless($paymentOrder->payer_uid === $this->uid($request), 403);

        return new PaymentOrderResource($this->payments->refreshFromProvider($paymentOrder));
    }

    public function cancel(Request $request, PaymentOrder $paymentOrder): JsonResource
    {
        abort_unless($paymentOrder->payer_uid === $this->uid($request), 403);
        abort_if($paymentOrder->settled_at !== null || $paymentOrder->status === 'completed', 409, 'Completed payments cannot be canceled.');
        abort_unless(in_array($paymentOrder->status, ['pending', 'processing', 'canceled'], true), 409, 'This payment can no longer be canceled.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return new PaymentOrderResource(
            $this->payments->cancelPayin(
                $paymentOrder,
                (string) ($data['reason'] ?? 'Canceled by student')
            )
        );
    }

    public function access(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contentId' => ['nullable', 'string', 'required_without:playlistId'],
            'playlistId' => ['nullable', 'string', 'required_without:contentId'],
        ]);

        $field = isset($data['playlistId']) ? 'playlistId' : 'contentId';
        $value = (string) $data[$field];

        $order = PaymentOrder::query()
            ->where('payer_uid', $this->uid($request))
            ->where('status', 'completed')
            ->where("metadata->{$field}", $value)
            ->latest('settled_at')
            ->first();

        return response()->json([
            'data' => [
                'hasAccess' => $order !== null,
                'paymentId' => $order?->public_id,
                'reference' => $order?->provider_reference,
                'settledAt' => $order?->settled_at?->toIso8601String(),
            ],
        ]);
    }

    private function uid(Request $request): string
    {
        return (string) $request->attributes->get('firebase_uid');
    }
}
