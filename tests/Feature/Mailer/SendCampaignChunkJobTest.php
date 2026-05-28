<?php

namespace Tests\Feature\Mailer;

use App\Jobs\SendCampaignChunkJob;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailTemplate;
use App\Models\User;
use App\Services\ResendMailerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendCampaignChunkJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_pending_recipients_and_updates_campaign_counters(): void
    {
        config()->set('services.resend.key', 'test_key');
        config()->set('services.resend.from', 'Sender <sender@example.com>');

        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'msg_123'], 200),
        ]);

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
            'recipient_count' => 2,
            'status' => 'queued',
        ]);

        $first = MailCampaignRecipient::query()->create([
            'mail_campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => 'one@example.com',
            'status' => 'pending',
            'unsubscribe_token' => 'token_1',
        ]);

        $second = MailCampaignRecipient::query()->create([
            'mail_campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => 'two@example.com',
            'status' => 'pending',
            'unsubscribe_token' => 'token_2',
        ]);

        $job = new SendCampaignChunkJob($campaign->id, [$first->id, $second->id]);
        $job->handle(app(ResendMailerService::class));

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);

        $this->assertDatabaseHas('mail_campaign_recipients', [
            'id' => $first->id,
            'status' => 'sent',
        ]);
    }
}
