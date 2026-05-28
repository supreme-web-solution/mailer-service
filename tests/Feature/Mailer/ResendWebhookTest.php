<?php

namespace Tests\Feature\Mailer;

use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_bounce_webhook_marks_recipient_and_suppresses_email(): void
    {
        config()->set('mailer.resend.webhook_secret', 'secret123');

        $user = User::factory()->create();
        $template = MailTemplate::query()->create([
            'user_id' => $user->id,
            'name' => 'Template',
            'subject' => 'Subject',
            'body' => '<p>Body</p>',
        ]);

        $campaign = MailCampaign::query()->create([
            'user_id' => $user->id,
            'mail_template_id' => $template->id,
            'subject' => $template->subject,
            'recipient_count' => 1,
            'status' => 'sent',
        ]);

        $recipient = MailCampaignRecipient::query()->create([
            'mail_campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => 'one@example.com',
            'status' => 'sent',
            'provider_message_id' => 'msg_123',
            'unsubscribe_token' => 'tok_123',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.bounced',
            'data' => [
                'email_id' => 'msg_123',
            ],
        ], [
            'x-mailer-webhook-secret' => 'secret123',
        ])->assertOk();

        $this->assertDatabaseHas('mail_campaign_recipients', [
            'id' => $recipient->id,
            'status' => 'bounced',
        ]);

        $this->assertDatabaseHas('mail_suppressions', [
            'user_id' => $user->id,
            'email' => 'one@example.com',
            'reason' => 'bounced',
        ]);
    }
}
