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
    public function sendEmail(string $toEmail, string $subject, string $html): array
    {
        $apiKey = trim((string) config('services.resend.key', ''));
        if ($apiKey === '') {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'RESEND_API_KEY is not configured.',
            ];
        }

        $from = trim((string) config('services.resend.from', config('mail.from.address')));
        $from = $from !== '' ? $from : 'onboarding@resend.dev';

        $response = $this->postEmailWithRetry($apiKey, [
            'from' => $from,
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $html,
        ]);

        if (! $response->successful()) {
            Log::warning('mailer.resend.send_failed', [
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

        return [
            'ok' => true,
            'message_id' => (string) data_get($response->json(), 'id', ''),
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
        $retryDelay = $retryAfter > 0
            ? min(30, max(1, $retryAfter))
            : min(30, (int) pow(2, $attempt + 1));

        sleep($retryDelay);

        return $this->postEmailWithRetry($apiKey, $payload, $attempt + 1);
    }
}
