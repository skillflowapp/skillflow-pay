import { Head, Link } from '@inertiajs/react';
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
    Clock,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type DashboardProps = {
    stats: {
        total_revenue: number;
        total_transactions: number;
        completed_transactions: number;
        pending_transactions: number;
        failed_transactions: number;
        total_withdrawals: number;
        completed_withdrawals: number;
        pending_withdrawals: number;
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
            return 'bg-[#00183d] text-primary border-primary';
        case 'failed':
        case 'rejected':
            return 'bg-red-950 text-red-400 border-red-500';
        case 'pending':
        case 'processing':
            return 'bg-amber-950 text-amber-400 border-amber-500';
        default:
            return 'bg-muted text-muted-foreground border-border';
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
        <Card className="border-border bg-card">
            <CardContent className="p-5">
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">{title}</p>
                        <p className="mt-1 text-2xl font-bold text-foreground">{value}</p>
                        {subtitle && <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>}
                        {trend && (
                            <div className={`mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${trendUp ? 'bg-[#00183d] text-primary' : 'bg-red-950 text-red-400'}`}>
                                {trendUp ? <ArrowUpRight className="h-3 w-3" /> : <ArrowDownRight className="h-3 w-3" />}
                                {trend}
                            </div>
                        )}
                    </div>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
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
                        <h1 className="text-2xl font-bold text-foreground">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">Overview of payments, payouts, and platform health.</p>
                    </div>
                    <Link
                        href={dashboard()}
                        className="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-muted-foreground transition hover:bg-muted"
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
                    <Card className="border-border bg-card lg:col-span-2">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Activity className="h-5 w-5 text-primary" />
                                <CardTitle className="text-foreground">Revenue Trend (30 Days)</CardTitle>
                            </div>
                            <CardDescription className="text-muted-foreground">Daily completed transaction totals.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-end gap-2">
                                {revenueByDay.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No completed transactions in the last 30 days.</p>
                                ) : (
                                    revenueByDay.map((day) => (
                                        <div key={day.date} className="flex flex-1 flex-col items-center gap-1">
                                            <div
                                                className="w-full rounded-t bg-primary transition-all hover:bg-[#3399FF]"
                                                style={{
                                                    height: `${Math.max((day.total / maxRevenue) * 160, 4)}px`,
                                                }}
                                                title={`${day.date}: ${formatMoney(day.total)}`}
                                            />
                                            <span className="text-[10px] text-muted-foreground">
                                                {new Date(day.date).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border bg-card">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5 text-primary" />
                                <CardTitle className="text-foreground">Status Breakdown</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {statusBreakdown.map((row) => {
                                const pct = stats.total_transactions > 0 ? Math.round((row.count / stats.total_transactions) * 100) : 0;

                                return (
                                    <div key={row.status}>
                                        <div className="mb-1 flex items-center justify-between text-sm">
                                            <span className="capitalize text-foreground">{row.status}</span>
                                            <span className="text-muted-foreground">{row.count} ({pct}%)</span>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={`h-full rounded-full ${
                                                    row.status === 'completed' ? 'bg-primary' : row.status === 'failed' ? 'bg-red-500' : 'bg-amber-500'
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
                    <Card className="border-border bg-card">
                        <CardHeader className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5 text-primary" />
                                <CardTitle className="text-foreground">Recent Transactions</CardTitle>
                            </div>
                            <Badge variant="outline" className="text-muted-foreground">{recentTransactions.length} shown</Badge>
                        </CardHeader>
                        <CardContent className="max-h-[420px] overflow-auto">
                            <div className="space-y-3">
                                {recentTransactions.map((tx) => (
                                    <div
                                        key={tx.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-muted p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-xs capitalize ${statusColor(tx.status)}`}
                                                >
                                                    {tx.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">{tx.type}</span>
                                            </div>
                                            <p className="mt-1 truncate text-sm font-medium text-foreground">
                                                {tx.reference}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {tx.created_at ? new Date(tx.created_at).toLocaleString() : ''}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold text-foreground">
                                                {formatMoney(tx.amount, tx.currency)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {tx.payee_uid ? `Payee: ${tx.payee_uid.slice(0, 12)}...` : 'Platform'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border bg-card">
                        <CardHeader className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Wallet className="h-5 w-5 text-primary" />
                                <CardTitle className="text-foreground">Recent Withdrawals</CardTitle>
                            </div>
                            <Badge variant="outline" className="text-muted-foreground">{recentWithdrawals.length} shown</Badge>
                        </CardHeader>
                        <CardContent className="max-h-[420px] overflow-auto">
                            <div className="space-y-3">
                                {recentWithdrawals.map((w) => (
                                    <div
                                        key={w.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-muted p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-xs capitalize ${statusColor(w.status)}`}
                                                >
                                                    {w.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground capitalize">{w.type}</span>
                                            </div>
                                            <p className="mt-1 truncate text-sm font-medium text-foreground">
                                                {w.reference}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {w.recipient_phone}
                                            </p>
                                            <p className="text-[10px] text-muted-foreground">
                                                {w.created_at ? new Date(w.created_at).toLocaleString() : ''}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold text-foreground">
                                                {formatMoney(w.amount, w.currency)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Payout {formatMoney(w.payout_amount, w.currency)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-border bg-card">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-primary" />
                                <CardTitle className="text-foreground">Platform Quick Stats</CardTitle>
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
                                    color: 'text-primary',
                                },
                                {
                                    label: 'Pending Txns',
                                    value: String(stats.pending_transactions),
                                    icon: Clock,
                                    color: 'text-amber-400',
                                },
                                {
                                    label: 'Pending Payouts',
                                    value: String(stats.pending_withdrawals),
                                    icon: Wallet,
                                    color: 'text-primary',
                                    href: '/admin/withdrawals',
                                },
                                {
                                    label: 'Avg Revenue / Txn',
                                    value: stats.total_transactions > 0 ? formatMoney(Math.round(stats.total_revenue / stats.total_transactions)) : formatMoney(0),
                                    icon: TrendingUp,
                                    color: 'text-primary',
                                },
                            ].map((item) => {
                                const cardContent = (
                                <div className="flex items-center gap-4 rounded-lg border border-border bg-muted p-4">
                                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg bg-muted ${item.color}`}>
                                        <item.icon className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">{item.label}</p>
                                        <p className="text-lg font-bold text-foreground">{item.value}</p>
                                    </div>
                                </div>
                                );

                                return item.href ? (
                                    <Link key={item.label} href={item.href} className="block transition hover:opacity-80">{cardContent}</Link>
                                ) : (
                                    <div key={item.label}>{cardContent}</div>
                                );
                            })}
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
