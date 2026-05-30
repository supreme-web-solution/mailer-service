<?php

namespace App\Jobs;

use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailSuppression;
use App\Services\ResendMailerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCampaignChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public int $campaignId,
        public array $recipientIds
    ) {
        $this->onQueue((string) config('mailer.queue', 'mailers'));
    }

    public function handle(ResendMailerService $mailer): void
    {
        Log::info('mailer.campaign_chunk.started', [
            'campaign_id' => $this->campaignId,
            'recipient_ids_count' => count($this->recipientIds),
            'queue' => $this->queue,
        ]);

        $campaign = MailCampaign::query()
            ->with(['template:id,subject,body'])
            ->find($this->campaignId);

        if (! $campaign || ! $campaign->template) {
            Log::warning('mailer.campaign_chunk.skipped', [
                'campaign_id' => $this->campaignId,
                'reason' => ! $campaign ? 'campaign_not_found' : 'template_missing',
            ]);

            return;
        }

        if ($campaign->status === 'queued') {
            $campaign->update(['status' => 'sending']);

            Log::info('mailer.campaign.status_changed', [
                'campaign_id' => $campaign->id,
                'status' => 'sending',
            ]);
        }

        $recipients = MailCampaignRecipient::query()
            ->where('mail_campaign_id', $campaign->id)
            ->whereIn('id', $this->recipientIds)
            ->where('status', 'pending')
            ->get();

        Log::info('mailer.campaign_chunk.processing', [
            'campaign_id' => $campaign->id,
            'pending_recipients' => $recipients->count(),
        ]);

        $sentCount = 0;
        $failedCount = 0;
        $suppressedCount = 0;

        /** @var MailCampaignRecipient $recipient */
        foreach ($recipients as $recipient) {
            $suppressed = MailSuppression::query()
                ->where('user_id', $campaign->user_id)
                ->where('email', $recipient->email)
                ->exists();

            if ($suppressed) {
                $recipient->update([
                    'status' => 'suppressed',
                    'failed_at' => now(),
                    'last_error' => 'Address is suppressed.',
                ]);

                $suppressedCount++;

                Log::info('mailer.recipient.suppressed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email,
                ]);

                continue;
            }

            Log::info('mailer.recipient.sending', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'subject' => $campaign->subject,
            ]);

            $result = $mailer->sendEmail(
                $recipient->email,
                $campaign->subject,
                $this->appendUnsubscribeLink($campaign->template->body, $recipient->unsubscribe_token),
                $campaign->id,
                $recipient->id,
            );

            if ($result['ok']) {
                $recipient->update([
                    'status' => 'sent',
                    'provider_message_id' => $result['message_id'],
                    'sent_at' => now(),
                    'last_error' => null,
                ]);

                $sentCount++;

                Log::info('mailer.recipient.sent', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email,
                    'provider_message_id' => $result['message_id'],
                ]);

                continue;
            }

            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => $result['error'],
            ]);

            $failedCount++;

            Log::warning('mailer.recipient.send_failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'error' => $result['error'],
            ]);
        }

        Log::info('mailer.campaign_chunk.completed', [
            'campaign_id' => $campaign->id,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'suppressed' => $suppressedCount,
        ]);

        $this->syncCampaignCounters($campaign);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('mailer.campaign_chunk.job_failed', [
            'campaign_id' => $this->campaignId,
            'recipient_ids_count' => count($this->recipientIds),
            'message' => $exception?->getMessage(),
        ]);
    }

    private function appendUnsubscribeLink(string $html, string $token): string
    {
        $unsubscribeUrl = route('mailer.unsubscribe', ['token' => $token]);

        return $html
            .'<hr style="margin:20px 0;border:none;border-top:1px solid #e5e7eb;">'
            .'<p style="font-size:12px;color:#6b7280;">'
            .'No longer want these emails? '
            .'<a href="'.$unsubscribeUrl.'" style="color:#2563eb;text-decoration:underline;">Unsubscribe</a>.'
            .'</p>';
    }

    private function syncCampaignCounters(MailCampaign $campaign): void
    {
        $totals = MailCampaignRecipient::query()
            ->where('mail_campaign_id', $campaign->id)
            ->selectRaw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_total")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_total")
            ->selectRaw("SUM(CASE WHEN status IN ('suppressed','unsubscribed') THEN 1 ELSE 0 END) as skipped_total")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_total")
            ->first();

        $sentTotal = (int) data_get($totals, 'sent_total', 0);
        $failedTotal = (int) data_get($totals, 'failed_total', 0);
        $pendingTotal = (int) data_get($totals, 'pending_total', 0);

        $status = $pendingTotal > 0
            ? 'sending'
            : ($failedTotal > 0 ? ($sentTotal > 0 ? 'partial' : 'failed') : 'sent');

        $campaign->update([
            'sent_count' => $sentTotal,
            'failed_count' => $failedTotal,
            'status' => $status,
            'sent_at' => $pendingTotal === 0 ? now() : null,
        ]);

        Log::info('mailer.campaign.counters_synced', [
            'campaign_id' => $campaign->id,
            'status' => $status,
            'sent_count' => $sentTotal,
            'failed_count' => $failedTotal,
            'pending_count' => $pendingTotal,
        ]);
    }
}
