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
            'firebase_project_id' => ['required', 'string'],
        ]);

        AppSetting::set('malipo_base_url', $validated['malipo_base_url'], 'api', 'string');
        AppSetting::setIfNotBlank('malipo_api_token', $validated['malipo_api_token'], 'api', 'string');
        AppSetting::setIfNotBlank('malipo_secret_key', $validated['malipo_secret_key'], 'api', 'string');
        AppSetting::set('malipo_webhook_secret', $validated['malipo_webhook_secret'] ?? '', 'api', 'string');
        AppSetting::set('malipo_timeout', (string) $validated['malipo_timeout'], 'api', 'integer');
        AppSetting::set('firebase_project_id', $validated['firebase_project_id'], 'api', 'string');

        // Update running config immediately so the next request sees it
        config([
            'malipo.base_url' => $validated['malipo_base_url'],
            'malipo.api_token' => AppSetting::get('malipo_api_token', config('malipo.api_token')),
            'malipo.secret_key' => AppSetting::get('malipo_secret_key', config('malipo.secret_key')),
            'malipo.webhook_secret' => $validated['malipo_webhook_secret'] ?? '',
            'malipo.timeout' => $validated['malipo_timeout'],
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

        if ($baseUrl && $token) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'apiToken' => $token,
                        'Authorization' => $secretKey ? "Bearer {$secretKey}" : '',
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

        $diagnostics = [
            'malipo_base_url' => $baseUrl,
            'malipo_api_token' => [
                'length' => strlen((string) $apiToken),
                'starts_with' => substr((string) $apiToken, 0, 8),
                'ends_with' => substr((string) $apiToken, -8),
                'contains_keyid' => str_contains((string) $apiToken, 'keyid'),
                'word_count' => count(explode(' ', trim((string) $apiToken))),
            ],
            'malipo_secret_key' => [
                'length' => strlen((string) $secretKey),
                'starts_with' => substr((string) $secretKey, 0, 8),
                'ends_with' => substr((string) $secretKey, -8),
            ],
        ];

        $testResult = null;
        if ($baseUrl && $apiToken) {
            try {
                $headers = ['apiToken' => $apiToken];
                if ($secretKey) {
                    $headers['Authorization'] = "Bearer {$secretKey}";
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
}
