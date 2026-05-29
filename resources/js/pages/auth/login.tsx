import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { Wallet, Lock, ArrowLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status }: Props) {
    return (
        <>
            <Head title="Admin Login — SkillFlow Pay" />

            <PasskeyVerify />

            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link href="/" className="flex items-center gap-2 text-sm text-slate-400 transition hover:text-white">
                                <ArrowLeft className="h-4 w-4" />
                                Back to home
                            </Link>

                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500 text-white">
                                <Wallet className="h-7 w-7" />
                            </div>

                            <div className="space-y-2 text-center">
                                <h1 className="text-2xl font-bold text-white">SkillFlow Pay</h1>
                                <p className="text-sm text-slate-400">
                                    Admin sign in to manage payments and payouts
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-700/50 bg-slate-800/40 p-6 backdrop-blur">
                            <Form
                                {...store.form()}
                                resetOnSuccess={['password']}
                                className="flex flex-col gap-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-5">
                                            <div className="grid gap-2">
                                                <Label htmlFor="email" className="text-slate-300">Email address</Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoFocus
                                                    tabIndex={1}
                                                    autoComplete="email"
                                                    placeholder="admin@pay.skillflowtz.com"
                                                    className="border-slate-600 bg-slate-900/60 text-white placeholder:text-slate-500 focus-visible:ring-emerald-500"
                                                />
                                                <InputError message={errors.email} />
                                            </div>

                                            <div className="grid gap-2">
                                                <div className="flex items-center justify-between">
                                                    <Label htmlFor="password" className="text-slate-300">Password</Label>
                                                </div>
                                                <div className="relative">
                                                    <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                                    <PasswordInput
                                                        id="password"
                                                        name="password"
                                                        required
                                                        tabIndex={2}
                                                        autoComplete="current-password"
                                                        placeholder="Enter your password"
                                                        className="border-slate-600 bg-slate-900/60 pl-9 text-white placeholder:text-slate-500 focus-visible:ring-emerald-500"
                                                    />
                                                </div>
                                                <InputError message={errors.password} />
                                            </div>

                                            <Button
                                                type="submit"
                                                className="mt-1 w-full bg-emerald-500 text-white hover:bg-emerald-400"
                                                tabIndex={4}
                                                disabled={processing}
                                                data-test="login-button"
                                            >
                                                {processing && <Spinner />}
                                                Sign in
                                            </Button>
                                        </div>

                                        {status && (
                                            <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-center text-sm font-medium text-emerald-300">
                                                {status}
                                            </div>
                                        )}
                                    </>
                                )}
                            </Form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Login.layout = {
    title: '',
    description: '',
};
