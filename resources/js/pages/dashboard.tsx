import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import {
    ArrowUpRight,
    ArrowDownRight,
    CreditCard,
    Wallet,
    Users,
    TrendingUp,
    Activity,
    RefreshCw,
    CheckCircle2,
    XCircle,
    Clock,
    ChevronRight,
} from 'lucide-react';

type DashboardProps = {
    stats: {
        total_revenue: number;
        total_transactions: number;
        completed_transactions: number;
        pending_transactions: number;
        failed_transactions: number;
        total_withdrawals: number;
        completed_withdrawals: number;
        total_teachers: number;
        total_students: number;
    };
    recentTransactions: Array<{
        id: string;
        type: string;
        status: string;
        amount: number;
        currency: string;
        payer_uid: string;
        payee_uid: string | null;
        reference: string;
        provider_reference: string | null;
        created_at: string;
        settled_at: string | null;
    }>;
    recentWithdrawals: Array<{
        id: string;
        type: string;
        status: string;
        amount: number;
        payout_amount: number;
        currency: string;
        owner_uid: string;
        recipient_phone: string;
        reference: string;
        created_at: string;
    }>;
    revenueByDay: Array<{ date: string; total: number }>;
    statusBreakdown: Array<{ status: string; count: number }>;
};

function formatMoney(amount: number, currency = 'TZS') {
    return `${currency} ${amount.toLocaleString()}`;
}

function statusColor(status: string) {
    switch (status) {
        case 'completed':
            return 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
        case 'failed':
            return 'bg-red-500/15 text-red-400 border-red-500/30';
        case 'rejected':
            return 'bg-red-500/15 text-red-400 border-red-500/30';
        case 'pending':
        case 'processing':
            return 'bg-amber-500/15 text-amber-400 border-amber-500/30';
        default:
            return 'bg-slate-500/15 text-slate-400 border-slate-500/30';
    }
}

function StatCard({
    title,
    value,
    subtitle,
    icon: Icon,
    trend,
    trendUp,
}: {
    title: string;
    value: string;
    subtitle?: string;
    icon: React.ElementType;
    trend?: string;
    trendUp?: boolean;
}) {
    return (
        <Card className="border-slate-700/50 bg-slate-800/40 backdrop-blur">
            <CardContent className="p-5">
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-sm font-medium text-slate-400">{title}</p>
                        <p className="mt-1 text-2xl font-bold text-white">{value}</p>
                        {subtitle && <p className="mt-1 text-xs text-slate-500">{subtitle}</p>}
                        {trend && (
                            <div className={`mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${trendUp ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'}`}>
                                {trendUp ? <ArrowUpRight className="h-3 w-3" /> : <ArrowDownRight className="h-3 w-3" />}
                                {trend}
                            </div>
                        )}
                    </div>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-300">
                        <Icon className="h-5 w-5" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    stats,
    recentTransactions,
    recentWithdrawals,
    revenueByDay,
    statusBreakdown,
}: DashboardProps) {
    const maxRevenue = Math.max(...revenueByDay.map((d) => d.total), 1);

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6 px-4 py-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Dashboard</h1>
                        <p className="text-sm text-slate-400">Overview of payments, payouts, and platform health.</p>
                    </div>
                    <Link
                        href={dashboard()}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-700"
                    >
                        <RefreshCw className="h-3.5 w-3.5" />
                        Refresh
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Revenue"
                        value={formatMoney(stats.total_revenue)}
                        subtitle="Completed transactions"
                        icon={TrendingUp}
                    />
                    <StatCard
                        title="Transactions"
                        value={String(stats.total_transactions)}
                        subtitle={`${stats.completed_transactions} completed · ${stats.pending_transactions} pending · ${stats.failed_transactions} failed`}
                        icon={CreditCard}
                    />
                    <StatCard
                        title="Withdrawals"
                        value={String(stats.total_withdrawals)}
                        subtitle={`${stats.completed_withdrawals} completed`}
                        icon={Wallet}
                    />
                    <StatCard
                        title="Users"
                        value={String(stats.total_teachers + stats.total_students)}
                        subtitle={`${stats.total_teachers} teachers · ${stats.total_students} students`}
                        icon={Users}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="border-slate-700/50 bg-slate-800/40 backdrop-blur lg:col-span-2">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Activity className="h-5 w-5 text-emerald-400" />
                                <CardTitle className="text-white">Revenue Trend (30 Days)</CardTitle>
                            </div>
                            <CardDescription className="text-slate-400">Daily completed transaction totals.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-end gap-2">
                                {revenueByDay.length === 0 ? (
                                    <p className="text-sm text-slate-500">No completed transactions in the last 30 days.</p>
                                ) : (
                                    revenueByDay.map((day) => (
                                        <div key={day.date} className="flex flex-1 flex-col items-center gap-1">
                                            <div
                                                className="w-full rounded-t bg-emerald-500/60 transition-all hover:bg-emerald-400"
                                                style={{
                                                    height: `${Math.max((day.total / maxRevenue) * 160, 4)}px`,
                                                }}
                                                title={`${day.date}: ${formatMoney(day.total)}`}
                                            />
                                            <span className="text-[10px] text-slate-500">
                                                {new Date(day.date).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-700/50 bg-slate-800/40 backdrop-blur">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5 text-emerald-400" />
                                <CardTitle className="text-white">Status Breakdown</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {statusBreakdown.map((row) => {
                                const pct = stats.total_transactions > 0 ? Math.round((row.count / stats.total_transactions) * 100) : 0;
                                return (
                                    <div key={row.status}>
                                        <div className="mb-1 flex items-center justify-between text-sm">
                                            <span className="capitalize text-slate-300">{row.status}</span>
                                            <span className="text-slate-400">{row.count} ({pct}%)</span>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-700">
                                            <div
                                                className={`h-full rounded-full ${
                                                    row.status === 'completed' ? 'bg-emerald-500' : row.status === 'failed' ? 'bg-red-500' : 'bg-amber-500'
                                                }`}
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="border-slate-700/50 bg-slate-800/40 backdrop-blur">
                        <CardHeader className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5 text-emerald-400" />
                                <CardTitle className="text-white">Recent Transactions</CardTitle>
                            </div>
                            <Badge variant="outline" className="text-slate-400">{recentTransactions.length} shown</Badge>
                        </CardHeader>
                        <CardContent className="max-h-[420px] overflow-auto">
                            <div className="space-y-3">
                                {recentTransactions.map((tx) => (
                                    <div
                                        key={tx.id}
                                        className="flex items-center justify-between rounded-lg border border-slate-700/40 bg-slate-900/40 p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-xs capitalize ${statusColor(tx.status)}`}
                                                >
                                                    {tx.status}
                                                </Badge>
                                                <span className="text-xs text-slate-500">{tx.type}</span>
                                            </div>
                                            <p className="mt-1 truncate text-sm font-medium text-white">
                                                {tx.reference}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {tx.created_at ? new Date(tx.created_at).toLocaleString() : ''}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold text-white">
                                                {formatMoney(tx.amount, tx.currency)}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {tx.payee_uid ? `Payee: ${tx.payee_uid.slice(0, 12)}...` : 'Platform'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-700/50 bg-slate-800/40 backdrop-blur">
                        <CardHeader className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Wallet className="h-5 w-5 text-emerald-400" />
                                <CardTitle className="text-white">Recent Withdrawals</CardTitle>
                            </div>
                            <Badge variant="outline" className="text-slate-400">{recentWithdrawals.length} shown</Badge>
                        </CardHeader>
                        <CardContent className="max-h-[420px] overflow-auto">
                            <div className="space-y-3">
                                {recentWithdrawals.map((w) => (
                                    <div
                                        key={w.id}
                                        className="flex items-center justify-between rounded-lg border border-slate-700/40 bg-slate-900/40 p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-xs capitalize ${statusColor(w.status)}`}
                                                >
                                                    {w.status}
                                                </Badge>
                                                <span className="text-xs text-slate-500 capitalize">{w.type}</span>
                                            </div>
                                            <p className="mt-1 truncate text-sm font-medium text-white">
                                                {w.reference}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {w.recipient_phone}
                                            </p>
                                            <p className="text-[10px] text-slate-600">
                                                {w.created_at ? new Date(w.created_at).toLocaleString() : ''}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold text-white">
                                                {formatMoney(w.amount, w.currency)}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                Payout {formatMoney(w.payout_amount, w.currency)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-slate-700/50 bg-slate-800/40 backdrop-blur">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-emerald-400" />
                                <CardTitle className="text-white">Platform Quick Stats</CardTitle>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                {
                                    label: 'Success Rate',
                                    value: stats.total_transactions > 0 ? `${Math.round((stats.completed_transactions / stats.total_transactions) * 100)}%` : '0%',
                                    icon: CheckCircle2,
                                    color: 'text-emerald-400',
                                },
                                {
                                    label: 'Pending / Processing',
                                    value: String(stats.pending_transactions),
                                    icon: Clock,
                                    color: 'text-amber-400',
                                },
                                {
                                    label: 'Failed / Rejected',
                                    value: String(stats.failed_transactions),
                                    icon: XCircle,
                                    color: 'text-red-400',
                                },
                                {
                                    label: 'Avg Revenue / Txn',
                                    value: stats.total_transactions > 0 ? formatMoney(Math.round(stats.total_revenue / stats.total_transactions)) : formatMoney(0),
                                    icon: TrendingUp,
                                    color: 'text-emerald-400',
                                },
                            ].map((item) => (
                                <div key={item.label} className="flex items-center gap-4 rounded-lg border border-slate-700/40 bg-slate-900/40 p-4">
                                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg bg-slate-700/50 ${item.color}`}>
                                        <item.icon className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-400">{item.label}</p>
                                        <p className="text-lg font-bold text-white">{item.value}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
