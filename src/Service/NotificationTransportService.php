<?php

declare(strict_types=1);

namespace App\Service;

class NotificationTransportService
{
    public function __construct(
        private readonly string $emailProvider,
        private readonly string $emailApiUrl,
        private readonly string $emailApiKey,
        private readonly string $pushProvider,
        private readonly string $pushApiUrl,
        private readonly string $pushApiKey,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function sendEmail(string $recipientEmail, string $title, string $body, array $payload = []): bool
    {
        return match (strtolower(trim($this->emailProvider))) {
            'webhook' => $this->sendWebhook($this->emailApiUrl, $this->emailApiKey, [
                'channel' => 'email',
                'recipientEmail' => $recipientEmail,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
            ]),
            'log' => $this->logOnly('email', [
                'recipientEmail' => $recipientEmail,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
            ]),
            default => true,
        };
    }

    /** @param array<string, mixed> $payload */
    public function sendPush(string $recipientUserId, string $title, string $body, array $payload = []): bool
    {
        return match (strtolower(trim($this->pushProvider))) {
            'webhook' => $this->sendWebhook($this->pushApiUrl, $this->pushApiKey, [
                'channel' => 'push',
                'recipientUserId' => $recipientUserId,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
            ]),
            'log' => $this->logOnly('push', [
                'recipientUserId' => $recipientUserId,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
            ]),
            default => true,
        };
    }

    /** @param array<string, mixed> $payload */
    private function sendWebhook(string $url, string $apiKey, array $payload): bool
    {
        $targetUrl = trim($url);
        if ($targetUrl === '') {
            return false;
        }

        try {
            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
            $ch = curl_init($targetUrl);
            if ($ch === false) {
                return false;
            }

            $headers = ['Content-Type: application/json'];
            if (trim($apiKey) !== '') {
                $headers[] = 'X-Provider-Key: ' . $apiKey;
            }

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
            ]);

            curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $payload */
    private function logOnly(string $channel, array $payload): bool
    {
        try {
            $encoded = json_encode(['channel' => $channel, 'payload' => $payload], JSON_THROW_ON_ERROR);
            error_log('[notification-' . $channel . '-log] ' . $encoded);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
