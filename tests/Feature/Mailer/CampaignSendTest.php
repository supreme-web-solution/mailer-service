<?php

namespace Tests\Feature\Mailer;

use App\Jobs\SendCampaignChunkJob;
use App\Models\MailContact;
use App\Models\MailContactBatch;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_send_queues_chunk_jobs_and_creates_recipients(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $template = MailTemplate::query()->create([
            'user_id' => $user->id,
            'name' => 'Template',
            'subject' => 'Subject',
            'body' => '<p>Body</p>',
        ]);

        MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'one@example.com',
            'is_active' => true,
        ]);

        MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'two@example.com',
            'is_active' => true,
        ]);

        $this->post('/mailer/send', [
            'mail_template_id' => $template->id,
            'recipient_mode' => 'all',
        ])->assertRedirect();

        $this->assertDatabaseCount('mail_campaign_recipients', 2);
        $this->assertDatabaseHas('mail_campaigns', [
            'user_id' => $user->id,
            'mail_template_id' => $template->id,
            'recipient_count' => 2,
            'sent_count' => 0,
            'failed_count' => 0,
            'status' => 'queued',
        ]);

        Queue::assertPushed(SendCampaignChunkJob::class, 1);
    }

    public function test_campaign_send_can_target_selected_batch(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $template = MailTemplate::query()->create([
            'user_id' => $user->id,
            'name' => 'Template',
            'subject' => 'Batch Subject',
            'body' => '<p>Body</p>',
        ]);

        $firstContact = MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'first@example.com',
            'is_active' => true,
        ]);

        $secondContact = MailContact::query()->create([
            'user_id' => $user->id,
            'email' => 'second@example.com',
            'is_active' => true,
        ]);

        $batch = MailContactBatch::query()->create([
            'user_id' => $user->id,
            'name' => 'VIP',
        ]);
        $batch->contacts()->sync([$secondContact->id]);

        $this->post('/mailer/send', [
            'mail_template_id' => $template->id,
            'recipient_mode' => 'batch',
            'recipient_batch_id' => $batch->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('mail_campaign_recipients', 1);
        $this->assertDatabaseHas('mail_campaign_recipients', [
            'mail_contact_id' => $secondContact->id,
            'email' => 'second@example.com',
        ]);
        $this->assertDatabaseMissing('mail_campaign_recipients', [
            'mail_contact_id' => $firstContact->id,
        ]);
    }
}
