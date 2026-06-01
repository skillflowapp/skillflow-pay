<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TextifySmsService
{
    private string $baseUrl = 'https://portal.textify.africa/api';

    public function send(string $to, string $content, ?string $senderName = null): array
    {
        $apiKey = (string) config('textify.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('TEXTIFY_API_KEY is not configured.');
        }

        $sender = $senderName ?? (string) config('textify.sender_name');
        $to = $this->normalizePhone($to);

        $payload = [
            'sender_name' => $sender,
            'messages' => [
                [
                    'receiver' => $to,
                    'content' => $content,
                ],
            ],
            'is_scheduled' => false,
            'scheduled_date' => null,
        ];

        Log::debug('Textify SMS request', [
            'to' => $to,
            'sender' => $sender,
        ]);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/message/create", $payload);

            $data = $response->json() ?? [];

            Log::debug('Textify SMS response', [
                'http_status' => $response->status(),
                'body' => $data,
            ]);

            if (! $response->successful()) {
                $message = $data['message'] ?? "Textify request failed with HTTP {$response->status()}.";
                throw new RuntimeException($message);
            }

            return (array) ($data['message'] ?? $data);
        } catch (RuntimeException $exception) {
            Log::error('Textify SMS failed', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    /**
     * Send admin notification for new withdrawal request.
     */
    public function notifyAdminOfWithdrawal(string $adminPhone, string $reference, int $amount, string $currency, string $ownerName, string $recipientPhone): bool
    {
        try {
            $content = "SkillFlow Pay: New payout request {$reference} for {$ownerName}. Amount: {$currency} {$amount}. Recipient: {$recipientPhone}. Login to admin to approve.";
            $this->send($adminPhone, $content);

            return true;
        } catch (RuntimeException $exception) {
            Log::error('Failed to send admin SMS notification', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * Send teacher notification for approved/completed withdrawal.
     */
    public function notifyUserOfWithdrawal(string $userPhone, string $reference, string $status, int $amount, string $currency): bool
    {
        try {
            $statusText = match ($status) {
                'completed' => 'completed successfully',
                'rejected' => 'rejected',
                default => $status,
            };
            $content = "SkillFlow Pay: Your payout request {$reference} has been {$statusText}. Amount: {$currency} {$amount}.";
            $this->send($userPhone, $content);

            return true;
        } catch (RuntimeException $exception) {
            Log::error('Failed to send user SMS notification', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        return str_starts_with($cleaned, '255') ? $cleaned : '255'.ltrim($cleaned, '0');
    }
}
