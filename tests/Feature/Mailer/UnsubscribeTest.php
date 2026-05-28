<?php

namespace Tests\Feature\Mailer;

use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsubscribe_link_creates_suppression_record(): void
    {
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
            'unsubscribe_token' => 'unsubscribe_token_1',
        ]);

        $this->get('/unsubscribe/'.$recipient->unsubscribe_token)
            ->assertOk();

        $this->assertDatabaseHas('mail_suppressions', [
            'user_id' => $user->id,
            'email' => 'one@example.com',
            'reason' => 'unsubscribed',
        ]);
    }
}
