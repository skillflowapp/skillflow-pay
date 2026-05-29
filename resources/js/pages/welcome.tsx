import { Head, Link, usePage, router } from '@inertiajs/react';
import { ArrowRight, CreditCard, Shield, Zap, BarChart3, Wallet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useEffect } from 'react';

export default function Welcome() {
    const { auth } = usePage().props as { auth?: { user?: unknown } };
    const isLoggedIn = !!auth?.user;

    useEffect(() => {
        if (!isLoggedIn) {
            router.get('/login');
        }
    }, [isLoggedIn]);

    if (!isLoggedIn) {
        return (
            <>
                <Head title="SkillFlow Pay" />
                <div className="flex min-h-screen flex-col items-center justify-center bg-background text-foreground">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                        <Wallet className="h-7 w-7" />
                    </div>
                    <p className="mt-4 text-sm text-muted-foreground">Redirecting to admin login...</p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="SkillFlow Pay" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <nav className="flex items-center justify-between border-b border-border px-6 py-5 lg:px-12">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <Wallet className="h-6 w-6" />
                        </div>
                        <span className="text-xl font-bold tracking-tight">SkillFlow Pay</span>
                    </div>
                    <div className="flex items-center gap-4">
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition"
                        >
                            Dashboard
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </nav>

                <main className="flex flex-1 flex-col items-center justify-center px-6 py-16 lg:px-12">
                    <div className="mx-auto max-w-4xl text-center">
                        <div className="mb-8 inline-flex items-center rounded-full border border-secondary bg-secondary px-4 py-1.5 text-sm font-medium text-secondary-foreground">
                            <Zap className="mr-2 h-4 w-4" />
                            Internal Admin Wrapper
                        </div>
                        <h1 className="mb-6 text-5xl font-extrabold tracking-tight text-foreground lg:text-7xl">
                            Payment Management
                        </h1>
                        <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-muted-foreground">
                            Internal payment orchestration for SkillFlow operations.
                        </p>
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Link href="/dashboard">
                                <Button size="lg" className="h-12 gap-2 bg-primary px-8 text-base">
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
                                title: 'Collections',
                                desc: 'Collect and manage incoming payment requests across multiple channels.',
                            },
                            {
                                icon: Shield,
                                title: 'Disbursements',
                                desc: 'Process and track outgoing payments with full audit controls.',
                            },
                            {
                                icon: BarChart3,
                                title: 'Analytics',
                                desc: 'Monitor transaction flows, success rates, and settlement health.',
                            },
                        ].map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-2xl border border-border bg-card p-6"
                            >
                                <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                                    <feature.icon className="h-5 w-5" />
                                </div>
                                <h3 className="mb-2 font-semibold text-foreground">{feature.title}</h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">{feature.desc}</p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="border-t border-border px-6 py-6 text-center text-sm text-muted-foreground lg:px-12">
                    <p>SkillFlow Pay</p>
                </footer>
            </div>
        </>
    );
}
