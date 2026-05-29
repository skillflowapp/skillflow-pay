import { Head, Link, usePage, router } from '@inertiajs/react';
import { login } from '@/routes';
import { ArrowRight, CreditCard, Shield, Zap, BarChart3, Wallet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useEffect } from 'react';

export default function Welcome() {
    const { auth } = usePage().props as { auth?: { user?: unknown } };
    const isLoggedIn = !!auth?.user;

    useEffect(() => {
        if (!isLoggedIn) {
            router.get(login());
        }
    }, [isLoggedIn]);

    if (!isLoggedIn) {
        return (
            <>
                <Head title="SkillFlow Pay" />
                <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500 text-white animate-pulse">
                        <Wallet className="h-7 w-7" />
                    </div>
                    <p className="mt-4 text-sm text-slate-400">Redirecting to admin login...</p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="SkillFlow Pay" />
            <div className="flex min-h-screen flex-col bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white">
                <nav className="flex items-center justify-between px-6 py-5 lg:px-12">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500 text-white">
                            <Wallet className="h-6 w-6" />
                        </div>
                        <span className="text-xl font-bold tracking-tight">SkillFlow Pay</span>
                    </div>
                    <div className="flex items-center gap-4">
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-400"
                        >
                            Dashboard
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </nav>

                <main className="flex flex-1 flex-col items-center justify-center px-6 py-16 lg:px-12">
                    <div className="mx-auto max-w-4xl text-center">
                        <div className="mb-8 inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-medium text-emerald-300">
                            <Zap className="mr-2 h-4 w-4" />
                            Tanzania Mobile Money Payment API
                        </div>
                        <h1 className="mb-6 text-5xl font-extrabold tracking-tight text-white lg:text-7xl">
                            SkillFlow Pay
                        </h1>
                        <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-slate-300">
                            Independent payment orchestration for SkillFlow — handle pay-ins, disbursements, wallet tracking, and Malipo Pay integration from one beautiful admin panel.
                        </p>
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Link href="/dashboard">
                                <Button size="lg" className="h-12 gap-2 bg-emerald-500 px-8 text-base hover:bg-emerald-400">
                                    Go to Dashboard
                                    <ArrowRight className="h-5 w-5" />
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <div className="mx-auto mt-20 grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            {
                                icon: CreditCard,
                                title: 'Mobile Money Collections',
                                desc: 'Accept payments via Airtel, M-Pesa, Mixx by Yas and Halotel through Malipo Pay.',
                            },
                            {
                                icon: Shield,
                                title: 'Secure Disbursements',
                                desc: 'Automated teacher and referral payouts with fund reservation and reversal safety.',
                            },
                            {
                                icon: BarChart3,
                                title: 'Real-Time Analytics',
                                desc: 'Track completed transactions, revenue trends, wallet balances and withdrawal status.',
                            },
                        ].map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-2xl border border-slate-700/50 bg-slate-800/40 p-6 backdrop-blur"
                            >
                                <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-300">
                                    <feature.icon className="h-5 w-5" />
                                </div>
                                <h3 className="mb-2 font-semibold text-white">{feature.title}</h3>
                                <p className="text-sm leading-relaxed text-slate-400">{feature.desc}</p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="border-t border-slate-800 px-6 py-6 text-center text-sm text-slate-500 lg:px-12">
                    <p>SkillFlow Pay — Powered by Malipo Pay API</p>
                </footer>
            </div>
        </>
    );
}
