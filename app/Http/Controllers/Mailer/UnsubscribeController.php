<?php

namespace App\Http\Controllers\Mailer;

use App\Http\Controllers\Controller;
use App\Models\MailCampaignRecipient;
use App\Models\MailSuppression;
use Illuminate\Http\Response;

class UnsubscribeController extends Controller
{
    public function __invoke(string $token): Response
    {
        $recipient = MailCampaignRecipient::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if (! $recipient) {
            return response('<h1>Invalid unsubscribe link.</h1>', 404)
                ->header('Content-Type', 'text/html');
        }

        MailSuppression::query()->updateOrCreate(
            [
                'user_id' => $recipient->user_id,
                'email' => $recipient->email,
            ],
            [
                'reason' => 'unsubscribed',
            ]
        );

        $recipient->update([
            'status' => 'unsubscribed',
            'failed_at' => now(),
            'last_error' => 'Recipient unsubscribed via link.',
        ]);

        return response('<h1>You have been unsubscribed successfully.</h1>', 200)
            ->header('Content-Type', 'text/html');
    }
}
