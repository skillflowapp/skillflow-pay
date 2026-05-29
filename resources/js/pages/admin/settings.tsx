import { Head, useForm, usePage, router } from '@inertiajs/react';
import React from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Shield, Server, Globe, Clock, Flame, Eye, EyeOff } from 'lucide-react';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
    credentials: {
        malipo_base_url: string;
        malipo_api_token: string;
        malipo_secret_key: string;
        malipo_webhook_secret: string;
        malipo_timeout: string;
        firebase_project_id: string;
    };
    flash?: {
        success?: string;
    };
    testResult?: {
        reachable?: boolean;
        message?: string;
        status?: number;
        body_preview?: string;
        error?: string;
    } | null;
    diagnostics?: {
        malipo_base_url: string;
        malipo_api_token: {
            length: number;
            starts_with: string;
            ends_with: string;
            contains_keyid: boolean;
            word_count: number;
        };
        malipo_secret_key: {
            length: number;
            starts_with: string;
            ends_with: string;
        };
    };
    errors?: Record<string, string>;
};

export default function AdminSettings() {
    const { credentials, flash, testResult, diagnostics } = usePage<PageProps>().props;
    const [showApiToken, setShowApiToken] = React.useState(false);
    const [showSecretKey, setShowSecretKey] = React.useState(false);

    const { data, setData, processing, errors, setError, clearErrors } = useForm({
        malipo_base_url: credentials.malipo_base_url,
        malipo_api_token: '',
        malipo_secret_key: '',
        malipo_webhook_secret: '',
        malipo_timeout: credentials.malipo_timeout,
        firebase_project_id: credentials.firebase_project_id,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();
        router.post('/admin/settings', data as Record<string, unknown>, {
            preserveScroll: true,
            onError: (errs) => {
                Object.entries(errs).forEach(([key, value]) => {
                    setError(key, value as string);
                });
            },
        });
    };

    const handleTest = () => {
        router.get('/admin/settings/connection-test', undefined, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Admin Settings" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-center gap-2">
                    <Shield className="h-6 w-6 text-primary" />
                    <Heading
                        title="Admin Settings"
                        description="Configure SkillFlow Pay API credentials"
                    />
                </div>

                {flash?.success && (
                    <Alert className="border-border bg-primary text-primary-foreground">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {testResult && (
                    <Alert className={testResult.reachable ? 'border-border bg-card text-foreground' : 'border-destructive bg-destructive text-destructive-foreground'}>
                        <AlertTitle>Connection Test</AlertTitle>
                        <AlertDescription>{testResult.message}</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                                <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Globe className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Payment Provider API</CardTitle>
                                </div>
                                <CardDescription>
                                    Configure your payment provider integration credentials.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="malipo_base_url">
                                        Base URL
                                        <Badge variant="secondary" className="ml-2">Required</Badge>
                                    </Label>
                                    <Input
                                        id="malipo_base_url"
                                        name="malipo_base_url"
                                        type="url"
                                        value={data.malipo_base_url}
                                        onChange={(e) => setData('malipo_base_url', e.target.value)}
                                        placeholder="https://api.malipopay.co.tz/api/v1"
                                        required
                                    />
                                    <InputError message={errors.malipo_base_url} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="malipo_api_token">
                                        API Token
                                        <Badge variant="secondary" className="ml-2">Required</Badge>
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="malipo_api_token"
                                            name="malipo_api_token"
                                            type={showApiToken ? 'text' : 'password'}
                                            value={data.malipo_api_token}
                                            onChange={(e) => setData('malipo_api_token', e.target.value)}
                                            placeholder="mp_sk_prod_..."
                                            required
                                            className="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setShowApiToken(!showApiToken)}
                                            tabIndex={-1}
                                        >
                                            {showApiToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Paste exactly as shown in your provider dashboard.
                                    </p>
                                    {credentials.malipo_api_token.includes('*') && (
                                        <p className="text-xs text-muted-foreground">
                                            Currently stored: {credentials.malipo_api_token}
                                        </p>
                                    )}
                                    <InputError message={errors.malipo_api_token} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="malipo_secret_key">
                                        Secret Key (Authorization)
                                        <Badge variant="secondary" className="ml-2">Required</Badge>
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="malipo_secret_key"
                                            name="malipo_secret_key"
                                            type={showSecretKey ? 'text' : 'password'}
                                            value={data.malipo_secret_key}
                                            onChange={(e) => setData('malipo_secret_key', e.target.value)}
                                            placeholder="mp_pk_prod_..."
                                            required
                                            className="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setShowSecretKey(!showSecretKey)}
                                            tabIndex={-1}
                                        >
                                            {showSecretKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                    {credentials.malipo_secret_key.includes('*') && (
                                        <p className="text-xs text-muted-foreground">
                                            Currently stored: {credentials.malipo_secret_key}
                                        </p>
                                    )}
                                    <InputError message={errors.malipo_secret_key} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="malipo_webhook_secret">
                                        Webhook Secret
                                        <Badge variant="outline" className="ml-2">Optional</Badge>
                                    </Label>
                                    <Input
                                        id="malipo_webhook_secret"
                                        name="malipo_webhook_secret"
                                        type="password"
                                        value={data.malipo_webhook_secret}
                                        onChange={(e) => setData('malipo_webhook_secret', e.target.value)}
                                        placeholder="Enter webhook secret for verification"
                                    />
                                    <InputError message={errors.malipo_webhook_secret} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="malipo_timeout">
                                        <span className="flex items-center gap-1">
                                            <Clock className="h-4 w-4" />
                                            Timeout (seconds)
                                        </span>
                                    </Label>
                                    <Input
                                        id="malipo_timeout"
                                        name="malipo_timeout"
                                        type="number"
                                        min={5}
                                        max={120}
                                        value={data.malipo_timeout}
                                        onChange={(e) => setData('malipo_timeout', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.malipo_timeout} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Flame className="h-5 w-5 text-orange-500" />
                                    <CardTitle>Firebase Config</CardTitle>
                                </div>
                                <CardDescription>
                                    Firebase settings for mobile app authentication.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="firebase_project_id">
                                        Project ID
                                        <Badge variant="secondary" className="ml-2">Required</Badge>
                                    </Label>
                                    <Input
                                        id="firebase_project_id"
                                        name="firebase_project_id"
                                        value={data.firebase_project_id}
                                        onChange={(e) => setData('firebase_project_id', e.target.value)}
                                        placeholder="skillflowapp-3c4f7"
                                        required
                                    />
                                    <InputError message={errors.firebase_project_id} />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing} data-test="update-api-settings">
                            Save API Credentials
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleTest}
                            disabled={processing}
                        >
                            <Server className="mr-2 h-4 w-4" />
                            Test Connection
                        </Button>
                    </div>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Webhook Endpoint</CardTitle>
                        <CardDescription>
                            Provide this endpoint to Malipo Pay dashboard to receive payment callbacks.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <code className="rounded-md bg-muted px-3 py-2 text-sm font-mono">
                            {typeof window !== 'undefined' ? `${window.location.origin}/api/v1/webhooks/malipo` : '/api/v1/webhooks/malipo'}
                        </code>
                        <p className="mt-2 text-xs text-muted-foreground">
                            This endpoint does not require a Firebase token. Configure your webhook secret above to verify incoming requests.
                        </p>
                    </CardContent>
                </Card>

                {diagnostics && (
                    <Card className="border-yellow-500/30">
                        <CardHeader>
                            <CardTitle>Diagnostics</CardTitle>
                            <CardDescription>
                                This card helps verify that credentials were stored correctly.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="text-sm">
                                <p className="font-medium">API Token</p>
                                <ul className="mt-1 list-inside list-disc text-muted-foreground">
                                    <li>Length: {diagnostics.malipo_api_token.length} chars</li>
                                    <li>Starts with: {diagnostics.malipo_api_token.starts_with}...</li>
                                    <li>Ends with: ...{diagnostics.malipo_api_token.ends_with}</li>
                                    <li>Contains "keyid": {diagnostics.malipo_api_token.contains_keyid ? 'Yes' : 'No'}</li>
                                    <li>Word count (spaces): {diagnostics.malipo_api_token.word_count}</li>
                                </ul>
                            </div>
                            <div className="text-sm">
                                <p className="font-medium">Secret Key</p>
                                <ul className="mt-1 list-inside list-disc text-muted-foreground">
                                    <li>Length: {diagnostics.malipo_secret_key.length} chars</li>
                                    <li>Starts with: {diagnostics.malipo_secret_key.starts_with}...</li>
                                    <li>Ends with: ...{diagnostics.malipo_secret_key.ends_with}</li>
                                </ul>
                            </div>
                            {testResult && (
                                <div className="text-sm">
                                    <p className="font-medium">API Health Check</p>
                                    {'error' in testResult ? (
                                        <p className="mt-1 text-red-500">Error: {testResult.error}</p>
                                    ) : (
                                        <>
                                            <p className="mt-1 text-muted-foreground">HTTP Status: {testResult.status}</p>
                                            <p className="text-muted-foreground">Body: {testResult.body_preview}</p>
                                        </>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

AdminSettings.layout = {
    breadcrumbs: [
        {
            title: 'Admin Settings',
            href: '',
        },
    ],
};
