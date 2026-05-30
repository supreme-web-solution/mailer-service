<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResendMailerService
{
    /**
     * @return array{ok:bool,message_id:?string,error:?string}
     */
    public function sendEmail(
        string $toEmail,
        string $subject,
        string $html,
        ?int $campaignId = null,
        ?int $recipientId = null,
    ): array {
        $apiKey = trim((string) config('services.resend.key', ''));
        if ($apiKey === '') {
            Log::error('mailer.resend.not_configured', [
                'campaign_id' => $campaignId,
                'recipient_id' => $recipientId,
                'email' => $toEmail,
            ]);

            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'RESEND_API_KEY is not configured.',
            ];
        }

        $from = trim((string) config('services.resend.from', config('mail.from.address')));
        $from = $from !== '' ? $from : 'onboarding@resend.dev';

        Log::info('mailer.resend.request', [
            'campaign_id' => $campaignId,
            'recipient_id' => $recipientId,
            'email' => $toEmail,
            'from' => $from,
            'subject' => $subject,
        ]);

        $response = $this->postEmailWithRetry($apiKey, [
            'from' => $from,
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $html,
        ]);

        if (! $response->successful()) {
            Log::warning('mailer.resend.send_failed', [
                'campaign_id' => $campaignId,
                'recipient_id' => $recipientId,
                'email' => $toEmail,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'message_id' => null,
                'error' => $response->body(),
            ];
        }

        $messageId = (string) data_get($response->json(), 'id', '');

        Log::info('mailer.resend.sent', [
            'campaign_id' => $campaignId,
            'recipient_id' => $recipientId,
            'email' => $toEmail,
            'provider_message_id' => $messageId,
        ]);

        return [
            'ok' => true,
            'message_id' => $messageId,
            'error' => null,
        ];
    }

    private function postEmailWithRetry(string $apiKey, array $payload, int $attempt = 0): Response
    {
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.resend.com/emails', $payload);

        if ($response->status() !== 429 && $response->status() < 500) {
            return $response;
        }

        if ($attempt >= (int) config('mailer.resend.max_retries', 3)) {
            return $response;
        }

        $retryAfter = (int) ($response->header('retry-after') ?? 0);
        Log::info('mailer.resend.retry', [
            'status' => $response->status(),
            'attempt' => $attempt + 1,
            'retry_after_seconds' => $retryAfter,
            'email' => $payload['to'][0] ?? null,
        ]);
        $retryDelay = $retryAfter > 0
            ? min(30, max(1, $retryAfter))
            : min(30, (int) pow(2, $attempt + 1));

        sleep($retryDelay);

        return $this->postEmailWithRetry($apiKey, $payload, $attempt + 1);
    }
}
