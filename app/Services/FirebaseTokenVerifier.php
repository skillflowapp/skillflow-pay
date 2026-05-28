<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class FirebaseTokenVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        if (app()->environment('testing') && str_starts_with($token, 'test-token:')) {
            $uid = substr($token, strlen('test-token:'));

            if ($uid !== '') {
                return ['sub' => $uid, 'uid' => $uid, 'aud' => config('firebase.project_id')];
            }
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthenticationException('Invalid Firebase token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJsonSegment($encodedHeader);
        $payload = $this->decodeJsonSegment($encodedPayload);

        if (($header['alg'] ?? null) !== 'RS256' || ! isset($header['kid'])) {
            throw new AuthenticationException('Unsupported Firebase token.');
        }

        $certificate = $this->certificates()[(string) $header['kid']] ?? null;
        if (! $certificate) {
            throw new AuthenticationException('Unknown Firebase token key.');
        }

        $verified = openssl_verify(
            "{$encodedHeader}.{$encodedPayload}",
            $this->base64UrlDecode($encodedSignature),
            $certificate,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new AuthenticationException('Firebase token signature verification failed.');
        }

        $projectId = (string) config('firebase.project_id');
        $issuer = "https://securetoken.google.com/{$projectId}";
        $now = time();

        if (($payload['aud'] ?? null) !== $projectId || ($payload['iss'] ?? null) !== $issuer) {
            throw new AuthenticationException('Firebase token project mismatch.');
        }

        if (! isset($payload['sub']) || ! is_string($payload['sub']) || $payload['sub'] === '') {
            throw new AuthenticationException('Firebase token is missing a subject.');
        }

        if (($payload['exp'] ?? 0) < $now || ($payload['iat'] ?? PHP_INT_MAX) > $now + 300) {
            throw new AuthenticationException('Firebase token is expired or not yet valid.');
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function certificates(): array
    {
        return Cache::remember('firebase_securetoken_certificates', now()->addHour(), function (): array {
            $response = Http::timeout(10)->get((string) config('firebase.certificates_url'));

            if (! $response->ok()) {
                throw new AuthenticationException('Could not load Firebase certificates.');
            }

            /** @var array<string, string> $certificates */
            $certificates = $response->json();

            return $certificates;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonSegment(string $segment): array
    {
        $json = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($json)) {
            throw new AuthenticationException('Invalid Firebase token payload.');
        }

        return $json;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new AuthenticationException('Invalid Firebase token encoding.');
        }

        return $decoded;
    }
}
