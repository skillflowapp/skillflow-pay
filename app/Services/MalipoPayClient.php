<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProviderTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
        $apiToken = (string) config('malipo.api_token');
        if ($apiToken === '') {
            throw new RuntimeException('MALIPO_API_TOKEN is not configured.');
        }

        $transaction = ProviderTransaction::create([
            'provider' => 'malipo',
            'direction' => $direction,
            'reference' => $reference,
            'request_payload' => $payload,
        ]);

        try {
            $response = Http::timeout((int) config('malipo.timeout'))
                ->acceptJson()
                ->withHeaders(['apiToken' => $apiToken])
                ->baseUrl(rtrim((string) config('malipo.base_url'), '/'))
                ->send($method, $path, $payload ? ['json' => $payload] : []);

            $data = $this->responseData($response);

            $transaction->update([
                'provider_reference' => $this->extractReference($data),
                'status' => $this->extractStatus($data),
                'amount' => $this->extractAmount($data),
                'currency' => $this->extractCurrency($data),
                'response_payload' => $data,
                'http_status' => $response->status(),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException($this->extractMessage($data) ?? "Malipo request failed with HTTP {$response->status()}.");
            }

            return $data;
        } catch (RuntimeException $exception) {
            $transaction->update(['error_message' => $exception->getMessage()]);
            throw $exception;
        }
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
        return $this->stringAt($data, ['message']) ?? $this->stringAt($data, ['error']);
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
