<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function index(): Response
    {
        $credentials = [
            'malipo_base_url' => AppSetting::get('malipo_base_url', config('malipo.base_url')),
            'malipo_api_token' => $this->maskSecret(AppSetting::get('malipo_api_token', config('malipo.api_token')), 20),
            'malipo_secret_key' => $this->maskSecret(AppSetting::get('malipo_secret_key', config('malipo.secret_key')), 20),
            'malipo_webhook_secret' => $this->maskSecret(AppSetting::get('malipo_webhook_secret', config('malipo.webhook_secret'))),
            'malipo_timeout' => AppSetting::get('malipo_timeout', (string) config('malipo.timeout', '20')),
            'textify_api_key' => $this->maskSecret(AppSetting::get('textify_api_key', config('textify.api_key')), 20),
            'textify_sender_name' => AppSetting::get('textify_sender_name', config('textify.sender_name', 'UPDATE')),
            'admin_notification_phone' => AppSetting::get('admin_notification_phone', config('notifications.admin_phone')),
            'firebase_project_id' => AppSetting::get('firebase_project_id', config('firebase.project_id')),
        ];

        return Inertia::render('admin/settings', [
            'credentials' => $credentials,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'malipo_base_url' => ['required', 'url'],
            'malipo_api_token' => ['required', 'string', 'min:8'],
            'malipo_secret_key' => ['required', 'string', 'min:8'],
            'malipo_webhook_secret' => ['nullable', 'string'],
            'malipo_timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'textify_api_key' => ['required', 'string', 'min:8'],
            'textify_sender_name' => ['required', 'string', 'min:1', 'max:11'],
            'admin_notification_phone' => ['required', 'string', 'regex:/^255[0-9]{9}$/'],
            'firebase_project_id' => ['required', 'string'],
        ]);

        AppSetting::set('malipo_base_url', $validated['malipo_base_url'], 'api', 'string');
        AppSetting::setIfNotBlank('malipo_api_token', $validated['malipo_api_token'], 'api', 'string');
        AppSetting::setIfNotBlank('malipo_secret_key', $validated['malipo_secret_key'], 'api', 'string');
        AppSetting::set('malipo_webhook_secret', $validated['malipo_webhook_secret'] ?? '', 'api', 'string');
        AppSetting::set('malipo_timeout', (string) $validated['malipo_timeout'], 'api', 'integer');
        AppSetting::setIfNotBlank('textify_api_key', $validated['textify_api_key'], 'api', 'string');
        AppSetting::set('textify_sender_name', $validated['textify_sender_name'], 'api', 'string');
        AppSetting::set('admin_notification_phone', $validated['admin_notification_phone'], 'api', 'string');
        AppSetting::set('firebase_project_id', $validated['firebase_project_id'], 'api', 'string');

        // Update running config immediately so the next request sees it
        config([
            'malipo.base_url' => $validated['malipo_base_url'],
            'malipo.api_token' => AppSetting::get('malipo_api_token', config('malipo.api_token')),
            'malipo.secret_key' => AppSetting::get('malipo_secret_key', config('malipo.secret_key')),
            'malipo.webhook_secret' => $validated['malipo_webhook_secret'] ?? '',
            'malipo.timeout' => $validated['malipo_timeout'],
            'textify.api_key' => AppSetting::get('textify_api_key', config('textify.api_key')),
            'textify.sender_name' => $validated['textify_sender_name'],
            'notifications.admin_phone' => $validated['admin_notification_phone'],
            'firebase.project_id' => $validated['firebase_project_id'],
        ]);

        return redirect()->route('admin.settings.index')->with('success', 'API credentials updated successfully.');
    }

    public function testConnection(): Response
    {
        $baseUrl = rtrim(AppSetting::get('malipo_base_url', config('malipo.base_url')), '/');
        $token = AppSetting::get('malipo_api_token', config('malipo.api_token'));
        $secretKey = AppSetting::get('malipo_secret_key', config('malipo.secret_key'));

        $reachable = false;
        $message = 'Credentials not configured.';

        [$apiToken, $authorizationKey] = $this->normalizedMalipoCredentials($token, $secretKey);

        if ($baseUrl && $apiToken) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'apiToken' => $apiToken,
                        'Authorization' => $authorizationKey ? "Bearer {$authorizationKey}" : '',
                    ])
                    ->get("{$baseUrl}/health");

                $reachable = $response->successful() || $response->status() === 404; // 404 means API exists, endpoint may not
                $message = $reachable ? 'Malipo Pay API appears reachable.' : "HTTP {$response->status()} response received.";
            } catch (\Throwable $e) {
                $message = 'Could not reach API: '.$e->getMessage();
            }
        }

        return Inertia::render('admin/settings', [
            'credentials' => $this->credentials(),
            'testResult' => ['reachable' => $reachable, 'message' => $message],
        ]);
    }

    public function diagnostics(): Response
    {
        $baseUrl = rtrim(AppSetting::get('malipo_base_url', config('malipo.base_url')), '/');
        $apiToken = AppSetting::get('malipo_api_token', config('malipo.api_token'));
        $secretKey = AppSetting::get('malipo_secret_key', config('malipo.secret_key'));
        [$resolvedApiToken, $resolvedAuthorizationKey] = $this->normalizedMalipoCredentials($apiToken, $secretKey);

        $diagnostics = [
            'malipo_base_url' => $baseUrl,
            'malipo_api_token' => [
                'length' => strlen($resolvedApiToken),
                'starts_with' => substr($resolvedApiToken, 0, 8),
                'ends_with' => substr($resolvedApiToken, -8),
                'contains_keyid' => str_contains($resolvedApiToken, 'keyid'),
                'word_count' => count(explode(' ', trim($resolvedApiToken))),
            ],
            'malipo_secret_key' => [
                'length' => strlen($resolvedAuthorizationKey),
                'starts_with' => substr($resolvedAuthorizationKey, 0, 8),
                'ends_with' => substr($resolvedAuthorizationKey, -8),
            ],
        ];

        $testResult = null;
        if ($baseUrl && $resolvedApiToken) {
            try {
                $headers = ['apiToken' => $resolvedApiToken];
                if ($resolvedAuthorizationKey) {
                    $headers['Authorization'] = "Bearer {$resolvedAuthorizationKey}";
                }
                $response = Http::timeout(10)->withHeaders($headers)->get("{$baseUrl}/health");
                $testResult = [
                    'status' => $response->status(),
                    'body_preview' => substr($response->body(), 0, 200),
                ];
            } catch (\Throwable $e) {
                $testResult = ['error' => $e->getMessage()];
            }
        }

        return Inertia::render('admin/settings', [
            'credentials' => $this->credentials(),
            'diagnostics' => $diagnostics,
            'testResult' => $testResult,
        ]);
    }

    private function credentials(): array
    {
        return [
            'malipo_base_url' => AppSetting::get('malipo_base_url', config('malipo.base_url')),
            'malipo_api_token' => $this->maskSecret(AppSetting::get('malipo_api_token', config('malipo.api_token')), 20),
            'malipo_secret_key' => $this->maskSecret(AppSetting::get('malipo_secret_key', config('malipo.secret_key')), 20),
            'malipo_webhook_secret' => $this->maskSecret(AppSetting::get('malipo_webhook_secret', config('malipo.webhook_secret'))),
            'malipo_timeout' => AppSetting::get('malipo_timeout', (string) config('malipo.timeout', '20')),
            'textify_api_key' => $this->maskSecret(AppSetting::get('textify_api_key', config('textify.api_key')), 20),
            'textify_sender_name' => AppSetting::get('textify_sender_name', config('textify.sender_name', 'UPDATE')),
            'admin_notification_phone' => AppSetting::get('admin_notification_phone', config('notifications.admin_phone')),
            'firebase_project_id' => AppSetting::get('firebase_project_id', config('firebase.project_id')),
        ];
    }

    private function maskSecret(?string $value, int $suffixKeep = 20): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        $keep = min($suffixKeep, (int) ($len / 2));
        $stars = max(4, $len - 4 - $keep);

        return substr($value, 0, 4).str_repeat('*', $stars).substr($value, -$keep);
    }

    /**
     * Malipo uses mp_sk_* for the apiToken header and mp_pk_* for the
     * Authorization bearer value. Normalize admin values the same way live
     * payment requests do, because older labels allowed them to be swapped.
     *
     * @return array{0: string, 1: string}
     */
    private function normalizedMalipoCredentials(?string ...$values): array
    {
        $apiToken = '';
        $authorizationKey = '';

        foreach ($values as $value) {
            $candidate = trim((string) $value);
            if ($candidate === '') {
                continue;
            }

            $withoutBearer = preg_replace('/^Bearer\s+/i', '', $candidate) ?? $candidate;

            if ($apiToken === '' && str_starts_with($withoutBearer, 'mp_sk_')) {
                $apiToken = $withoutBearer;
            }

            if ($authorizationKey === '' && str_starts_with($withoutBearer, 'mp_pk_')) {
                $authorizationKey = $withoutBearer;
            }
        }

        return [$apiToken, $authorizationKey];
    }
}
