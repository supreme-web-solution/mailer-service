<?php

namespace App\Http\Controllers\Mailer;

use App\Http\Controllers\Controller;
use App\Models\MailCampaignRecipient;
use App\Models\MailSuppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResendWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = trim((string) config('mailer.resend.webhook_secret', ''));
        if ($secret !== '') {
            $provided = trim((string) $request->header('x-mailer-webhook-secret', ''));
            if (! hash_equals($secret, $provided)) {
                return response()->json(['ok' => false], 401);
            }
        }

        $payload = $request->all();
        $events = is_array($payload) && array_is_list($payload) ? $payload : [$payload];

        foreach ($events as $event) {
            $eventType = strtolower((string) data_get($event, 'type', ''));
            $providerMessageId = (string) data_get($event, 'data.email_id', data_get($event, 'email_id', ''));
            $toEmail = strtolower((string) data_get($event, 'data.to', data_get($event, 'to', '')));

            $recipient = MailCampaignRecipient::query()
                ->when($providerMessageId !== '', fn ($query) => $query->where('provider_message_id', $providerMessageId))
                ->when(
                    $providerMessageId === '' && $toEmail !== '',
                    fn ($query) => $query->where('email', $toEmail)->latest('id')
                )
                ->first();

            if (! $recipient) {
                continue;
            }

            if (in_array($eventType, ['email.delivered', 'delivered'], true)) {
                $recipient->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                    'last_error' => null,
                ]);

                continue;
            }

            if (in_array($eventType, ['email.bounced', 'bounced'], true)) {
                $recipient->update([
                    'status' => 'bounced',
                    'failed_at' => now(),
                    'last_error' => 'Bounced by provider.',
                ]);
                $this->suppress($recipient->user_id, $recipient->email, 'bounced');

                continue;
            }

            if (in_array($eventType, ['email.complained', 'complained'], true)) {
                $recipient->update([
                    'status' => 'complained',
                    'failed_at' => now(),
                    'last_error' => 'Spam complaint.',
                ]);
                $this->suppress($recipient->user_id, $recipient->email, 'complained');
            }
        }

        return response()->json(['ok' => true]);
    }

    private function suppress(int $userId, string $email, string $reason): void
    {
        MailSuppression::query()->updateOrCreate(
            ['user_id' => $userId, 'email' => strtolower($email)],
            ['reason' => $reason]
        );
    }
}
