<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $period = now()->subDays(30);

        $orders = PaymentOrder::query();
        $withdrawals = WithdrawalRequest::query();

        $stats = [
            'total_revenue' => (int) (clone $orders)->where('status', 'completed')->sum('amount'),
            'total_transactions' => (clone $orders)->count(),
            'completed_transactions' => (clone $orders)->where('status', 'completed')->count(),
            'pending_transactions' => (clone $orders)->whereIn('status', ['pending', 'processing'])->count(),
            'failed_transactions' => (clone $orders)->where('status', 'failed')->count(),
            'total_withdrawals' => (clone $withdrawals)->count(),
            'completed_withdrawals' => (clone $withdrawals)->where('status', 'completed')->count(),
            'total_teachers' => (int) DB::table('wallet_accounts')->where('owner_type', 'teacher')->count(),
            'total_students' => (int) DB::table('wallet_accounts')->where('owner_type', 'referral')->count(),
        ];

        $recentTransactions = PaymentOrder::query()
            ->with(['providerTransactions'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->public_id,
                'type' => $order->type,
                'status' => $order->status,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'payer_uid' => $order->payer_uid,
                'payee_uid' => $order->payee_uid,
                'reference' => $order->reference,
                'provider_reference' => $order->provider_reference,
                'created_at' => $order->created_at?->toIso8601String(),
                'settled_at' => $order->settled_at?->toIso8601String(),
            ]);

        $recentWithdrawals = WithdrawalRequest::query()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($w) => [
                'id' => $w->public_id,
                'type' => $w->type,
                'status' => $w->status,
                'amount' => $w->requested_amount,
                'payout_amount' => $w->payout_amount,
                'currency' => $w->currency,
                'owner_uid' => $w->owner_uid,
                'recipient_phone' => $w->recipient_phone,
                'reference' => $w->reference,
                'created_at' => $w->created_at?->toIso8601String(),
            ]);

        $revenueByDay = PaymentOrder::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'))
            ->where('status', 'completed')
            ->where('created_at', '>=', $period)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
            ]);

        $statusBreakdown = PaymentOrder::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ]);

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'recentWithdrawals' => $recentWithdrawals,
            'revenueByDay' => $revenueByDay,
            'statusBreakdown' => $statusBreakdown,
        ]);
    }
}
