<?php

namespace App\Http\Controllers\Mailer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailer\CampaignSendRequest;
use App\Jobs\SendCampaignChunkJob;
use App\Models\MailCampaignRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CampaignController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        return redirect()
            ->route('mailer.contacts.index')
            ->with('status', 'open-a-batch-to-send');
    }

    public function store(CampaignSendRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $template = $user?->mailTemplates()->findOrFail((int) $validated['mail_template_id']);

        $contactsQuery = $user?->mailContacts()
            ->where('is_active', true)
            ->whereNotIn('email', function ($query) use ($user): void {
                $query->from('mail_suppressions')
                    ->select('email')
                    ->where('user_id', $user?->id);
            });

        if (($validated['recipient_mode'] ?? 'all') === 'selected') {
            $ids = collect($validated['recipient_ids'] ?? [])->map(fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                return back()->withErrors(['recipient_ids' => 'Select at least one recipient.']);
            }
            $contactsQuery?->whereIn('id', $ids);
        }

        if (($validated['recipient_mode'] ?? 'all') === 'batch') {
            $batchId = (int) ($validated['recipient_batch_id'] ?? 0);

            if ($batchId <= 0) {
                return back()->withErrors(['recipient_batch_id' => 'Select a batch first.']);
            }

            $batchExists = $user?->mailContactBatches()
                ->whereKey($batchId)
                ->exists();

            if (! $batchExists) {
                return back()->withErrors(['recipient_batch_id' => 'Selected batch was not found.']);
            }

            $contactsQuery?->whereHas('batches', function ($query) use ($batchId): void {
                $query->where('mail_contact_batches.id', $batchId);
            });
        }

        $recipientCount = (int) ($contactsQuery?->count() ?? 0);
        if ($recipientCount === 0) {
            return back()->withErrors(['recipient_mode' => 'No recipients available for this campaign.']);
        }

        $campaign = $user?->mailCampaigns()->create([
            'mail_template_id' => $template->id,
            'subject' => $template->subject,
            'recipient_count' => $recipientCount,
            'status' => 'queued',
        ]);

        $chunkSize = max(1, (int) config('mailer.chunk_size', 200));
        $contactsQuery?->orderBy('id')->chunkById($chunkSize, function ($contacts) use ($campaign): void {
            $rows = [];
            $chunkEmails = [];
            $now = now();

            foreach ($contacts as $contact) {
                $chunkEmails[] = $contact->email;
                $rows[] = [
                    'mail_campaign_id' => $campaign->id,
                    'mail_contact_id' => $contact->id,
                    'user_id' => $campaign->user_id,
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'status' => 'pending',
                    'unsubscribe_token' => Str::random(48),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows === []) {
                return;
            }

            DB::table('mail_campaign_recipients')->insert($rows);

            $recipientIds = MailCampaignRecipient::query()
                ->where('mail_campaign_id', $campaign->id)
                ->whereIn('email', $chunkEmails)
                ->pluck('id')
                ->all();

            if ($recipientIds !== []) {
                SendCampaignChunkJob::dispatch($campaign->id, $recipientIds);
            }
        });

        return back()->with('status', 'campaign-queued');
    }
}
