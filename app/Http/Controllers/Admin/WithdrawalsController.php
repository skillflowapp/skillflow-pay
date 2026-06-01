<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WithdrawalsController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');
        $query = WithdrawalRequest::query()
            ->when(in_array($status, ['pending', 'approved', 'completed', 'rejected'], true), function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $counts = [
            'pending' => WithdrawalRequest::where('status', 'pending')->count(),
            'approved' => WithdrawalRequest::where('status', 'approved')->count(),
            'completed' => WithdrawalRequest::where('status', 'completed')->count(),
            'rejected' => WithdrawalRequest::where('status', 'rejected')->count(),
        ];

        return Inertia::render('admin/withdrawals', [
            'withdrawals' => $query,
            'counts' => $counts,
            'filter' => $status,
        ]);
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        try {
            $this->withdrawals->approve($withdrawal);
        } catch (\Throwable $e) {
            return redirect()->route('admin.withdrawals.index')->with('error', "Approval failed: {$e->getMessage()}");
        }

        return redirect()->route('admin.withdrawals.index')->with('success', "Withdrawal {$withdrawal->reference} approved and disbursement sent.");
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $reason = $request->input('reason', 'Rejected by admin');

        try {
            $this->withdrawals->reject($withdrawal, $reason);
        } catch (\Throwable $e) {
            return redirect()->route('admin.withdrawals.index')->with('error', "Rejection failed: {$e->getMessage()}");
        }

        return redirect()->route('admin.withdrawals.index')->with('success', "Withdrawal {$withdrawal->reference} rejected and funds reversed.");
    }
}
