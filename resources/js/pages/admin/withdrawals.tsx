import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle,
    Clock,
    RefreshCw,
    Wallet,
    XCircle,
} from 'lucide-react';

import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Withdrawal = {
    id: string;
    public_id: string;
    reference: string;
    type: string;
    status: string;
    owner_uid: string;
    owner_name: string;
    requested_amount: number;
    platform_fee: number;
    payout_amount: number;
    currency: string;
    payment_method: string;
    recipient_phone: string;
    failure_reason: string | null;
    created_at: string;
    processed_at: string | null;
    completed_at: string | null;
};

type PageProps = {
    auth: { user: { is_admin?: boolean } };
    withdrawals: {
        data: Withdrawal[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    counts: {
        pending: number;
        approved: number;
        completed: number;
        rejected: number;
    };
    filter: string;
    flash?: {
        success?: string;
        error?: string;
    };
};

function statusBadge(status: string) {
    switch (status) {
        case 'completed':
            return 'bg-primary text-primary-foreground';
        case 'approved':
            return 'bg-secondary text-secondary-foreground';
        case 'pending':
            return 'bg-amber-500 text-white';
        case 'rejected':
            return 'bg-red-500 text-white';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

export default function AdminWithdrawals() {
    const { withdrawals, counts, filter, flash } = usePage<PageProps>().props;

    const handleFilter = (status: string) => {
        router.get('/admin/withdrawals', { status }, { preserveScroll: true });
    };

    const handleApprove = (id: string) => {
        if (!confirm('Approve this withdrawal and send disbursement?')) {
            return;
        }

        router.post(`/admin/withdrawals/${id}/approve`, {}, { preserveScroll: true });
    };

    const handleReject = (id: string) => {
        if (!confirm('Reject this withdrawal and reverse reserved funds?')) {
            return;
        }

        router.post(`/admin/withdrawals/${id}/reject`, {}, { preserveScroll: true });
    };

    const formatMoney = (amount: number, currency: string) =>
        `${currency} ${amount.toLocaleString()}`;

    return (
        <>
            <Head title="Withdrawals" />
            <div className="space-y-6 px-4 py-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Wallet className="h-6 w-6 text-primary" />
                        <Heading
                            title="Withdrawals"
                            description="Review and process pending payout requests."
                        />
                    </div>
                    <Button variant="outline" onClick={() => router.reload()}>
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>

                {flash?.success && (
                    <Alert className="border-border bg-primary text-primary-foreground">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}
                {flash?.error && (
                    <Alert className="border-destructive bg-destructive text-destructive-foreground">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: 'Pending', count: counts.pending, icon: Clock, color: 'text-amber-400' },
                        { label: 'Approved', count: counts.approved, icon: CheckCircle, color: 'text-secondary' },
                        { label: 'Completed', count: counts.completed, icon: CheckCircle, color: 'text-primary' },
                        { label: 'Rejected', count: counts.rejected, icon: XCircle, color: 'text-red-400' },
                    ].map((item) => (
                        <Card
                            key={item.label}
                            className={`cursor-pointer border-border bg-card transition hover:bg-muted ${filter === item.label.toLowerCase() ? 'ring-2 ring-primary' : ''}`}
                            onClick={() => handleFilter(item.label.toLowerCase())}
                        >
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">{item.label}</p>
                                        <p className="mt-1 text-2xl font-bold text-foreground">{item.count}</p>
                                    </div>
                                    <item.icon className={`h-6 w-6 ${item.color}`} />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card className="border-border bg-card">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-foreground">Requests</CardTitle>
                            <Badge variant="outline" className="text-muted-foreground">
                                {withdrawals.total} total
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="max-h-[600px] overflow-auto">
                        {withdrawals.data.length === 0 ? (
                            <p className="py-6 text-center text-muted-foreground">No withdrawals found.</p>
                        ) : (
                            <div className="space-y-3">
                                {withdrawals.data.map((w) => (
                                    <div
                                        key={w.id}
                                        className="flex flex-col gap-3 rounded-lg border border-border bg-muted p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <Badge className={`text-xs capitalize ${statusBadge(w.status)}`}>
                                                    {w.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">{w.reference}</span>
                                            </div>
                                            <p className="mt-1 text-sm font-medium text-foreground">
                                                {w.owner_name} ({w.type})
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {w.recipient_phone} · {w.payment_method}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(w.created_at).toLocaleString()}
                                            </p>
                                            {w.failure_reason && (
                                                <p className="text-xs text-red-400">{w.failure_reason}</p>
                                            )}
                                        </div>

                                        <div className="text-right sm:w-48">
                                            <p className="text-sm font-semibold text-foreground">
                                                {formatMoney(w.payout_amount, w.currency)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Fee {formatMoney(w.platform_fee, w.currency)} · Req {formatMoney(w.requested_amount, w.currency)}
                                            </p>
                                        </div>

                                        {w.status === 'pending' && (
                                            <div className="flex items-center gap-2 sm:justify-end">
                                                <Button
                                                    size="sm"
                                                    className="bg-primary text-primary-foreground"
                                                    onClick={() => handleApprove(w.id)}
                                                >
                                                    <CheckCircle className="mr-1 h-4 w-4" />
                                                    Approve
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => handleReject(w.id)}
                                                >
                                                    <XCircle className="mr-1 h-4 w-4" />
                                                    Reject
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex items-center justify-between">
                    <p className="text-xs text-muted-foreground">
                        Showing {withdrawals.from}–{withdrawals.to} of {withdrawals.total}
                    </p>
                    <div className="flex items-center gap-2">
                        {withdrawals.links.map((link, i) =>
                            link.url ? (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => router.get(link.url!, {}, { preserveScroll: true })}
                                >
                                    {link.label === '&laquo; Previous' ? (
                                        <ArrowLeft className="h-4 w-4" />
                                    ) : link.label === 'Next &raquo;' ? (
                                        <ArrowRight className="h-4 w-4" />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ) : null
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

AdminWithdrawals.layout = {
    breadcrumbs: [
        {
            title: 'Withdrawals',
            href: '',
        },
    ],
};
