<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProviderTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class MalipoPayClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function collect(string $reference, array $payload): array
    {
        return $this->request('collection', $reference, 'POST', '/payment/collection', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function disburse(string $reference, array $payload): array
    {
        return $this->request('disbursement', $reference, 'POST', '/payment/disbursement', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $reference): array
    {
        return $this->request('verify', $reference, 'GET', '/payment/verify/'.rawurlencode($reference));
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupByReference(string $reference): array
    {
        return $this->request('lookup', $reference, 'GET', '/payment/reference/'.rawurlencode($reference));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(
        string $direction,
        string $reference,
        string $method,
        string $path,
        ?array $payload = null,
    ): array {
        [$apiToken, $publicKey] = $this->resolveCredentials();

        if ($apiToken === '') {
            throw new RuntimeException('Malipo apiToken credential is not configured. Expected an mp_sk_ value.');
        }

        if ($publicKey === '') {
            throw new RuntimeException('Malipo public bearer credential is not configured. Expected an mp_pk_ value.');
        }

        $transaction = ProviderTransaction::create([
            'provider' => 'malipo',
            'direction' => $direction,
            'reference' => $reference,
            'request_payload' => $payload,
        ]);

        try {
            $baseUrl = rtrim((string) config('malipo.base_url'), '/');
            $url = "{$baseUrl}{$path}";
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'apiToken' => $apiToken,
            ];
            $headers['Authorization'] = str_starts_with($publicKey, 'Bearer ')
                ? $publicKey
                : "Bearer {$publicKey}";

            Log::debug('Malipo request', [
                'method' => $method,
                'url' => $url,
                'headers' => array_diff_key($headers, array_flip(['apiToken', 'Authorization'])), // mask secrets
                'credential_diagnostics' => [
                    'apiToken_prefix' => substr($apiToken, 0, 6),
                    'apiToken_length' => strlen($apiToken),
                    'authorization_prefix' => substr($publicKey, 0, 6),
                    'authorization_length' => strlen($publicKey),
                ],
                'payload' => $payload,
            ]);

            $http = Http::timeout((int) config('malipo.timeout'))
                ->asJson()
                ->withHeaders($headers);

            if ($method === 'GET') {
                $response = $http->get($url);
            } else {
                $response = $http->post($url, $payload ?? []);
            }

            $rawBody = $response->body();
            $data = $this->responseData($response);

            Log::debug('Malipo response', [
                'http_status' => $response->status(),
                'raw_body' => $rawBody,
                'parsed_data' => $data,
            ]);

            $transaction->update([
                'provider_reference' => $this->extractReference($data),
                'status' => $this->extractStatus($data),
                'amount' => $this->extractAmount($data),
                'currency' => $this->extractCurrency($data),
                'response_payload' => $data,
                'http_status' => $response->status(),
            ]);

            if (! $response->successful()) {
                $message = $this->extractMessage($data);
                if ($message === null || $message === '') {
                    $message = "Malipo request failed with HTTP {$response->status()}. Raw: {$rawBody}";
                }
                throw new RuntimeException($message);
            }

            return $data;
        } catch (RuntimeException $exception) {
            $transaction->update(['error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    /**
     * Malipo's examples use mp_sk_* as the apiToken header and mp_pk_* as the
     * Authorization bearer value. Admin-stored settings from earlier builds may
     * have those labels reversed, so normalize by prefix before sending.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveCredentials(): array
    {
        $candidates = [
            (string) config('malipo.api_token'),
            (string) config('malipo.public_key'),
            (string) config('malipo.secret_key'),
        ];

        $apiToken = '';
        $publicKey = '';

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if ($value === '') {
                continue;
            }

            $withoutBearer = preg_replace('/^Bearer\s+/i', '', $value) ?? $value;

            if ($apiToken === '' && str_starts_with($withoutBearer, 'mp_sk_')) {
                $apiToken = $withoutBearer;
            }

            if ($publicKey === '' && str_starts_with($withoutBearer, 'mp_pk_')) {
                $publicKey = $withoutBearer;
            }
        }

        if ($apiToken === '') {
            $apiToken = trim((string) config('malipo.api_token'));
        }

        if ($publicKey === '') {
            $publicKey = preg_replace('/^Bearer\s+/i', '', trim((string) config('malipo.public_key'))) ?? '';
        }

        return [$apiToken, $publicKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(Response $response): array
    {
        $data = $response->json();

        if (is_array($data)) {
            return $data;
        }

        return ['message' => $response->body()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractReference(array $data): ?string
    {
        return $this->stringAt($data, ['reference'])
            ?? $this->stringAt($data, ['data', 'reference'])
            ?? $this->stringAt($data, ['payment', 'reference']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractStatus(array $data): ?string
    {
        return $this->stringAt($data, ['status'])
            ?? $this->stringAt($data, ['data', 'status'])
            ?? $this->stringAt($data, ['payment', 'status']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractExternalReference(array $data): ?string
    {
        return $this->stringAt($data, ['external_reference'])
            ?? $this->stringAt($data, ['externalReference'])
            ?? $this->stringAt($data, ['data', 'external_reference']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractLink(array $data): ?string
    {
        return $this->stringAt($data, ['link']) ?? $this->stringAt($data, ['data', 'link']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractAmount(array $data): ?int
    {
        $value = $this->valueAt($data, ['amount']) ?? $this->valueAt($data, ['data', 'amount']);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractCurrency(array $data): ?string
    {
        return $this->stringAt($data, ['currency']) ?? $this->stringAt($data, ['data', 'currency']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractMessage(array $data): ?string
    {
        $top = $this->stringAt($data, ['message']) ?? $this->stringAt($data, ['error']);
        if ($top !== null) {
            $nested = $this->extractNestedErrors($data);
            if ($nested !== '') {
                return "{$top}: {$nested}";
            }

            return $top;
        }

        return $this->extractNestedErrors($data) ?: null;
    }

    private function extractNestedErrors(array $data): string
    {
        $errors = $this->valueAt($data, ['errors'])
            ?? $this->valueAt($data, ['data', 'errors'])
            ?? $this->valueAt($data, ['validation'])
            ?? [];

        if (! is_array($errors) || $errors === []) {
            return '';
        }

        $parts = [];
        foreach ($errors as $key => $value) {
            if (is_string($value)) {
                $parts[] = "{$key}: {$value}";
            } elseif (is_array($value)) {
                $parts[] = "{$key}: ".implode(', ', $value);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $path
     */
    private function stringAt(array $data, array $path): ?string
    {
        $value = $this->valueAt($data, $path);

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $path
     */
    private function valueAt(array $data, array $path): mixed
    {
        $cursor = $data;
        foreach ($path as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
