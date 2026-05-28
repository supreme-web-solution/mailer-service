<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => [
                'contacts' => $user?->mailContacts()->count() ?? 0,
                'templates' => $user?->mailTemplates()->count() ?? 0,
                'campaigns' => $user?->mailCampaigns()->count() ?? 0,
                'sent_total' => (int) ($user?->mailCampaigns()->sum('sent_count') ?? 0),
            ],
            'recentCampaigns' => $user?->mailCampaigns()
                ->latest('sent_at')
                ->limit(5)
                ->get(['id', 'subject', 'recipient_count', 'sent_count', 'failed_count', 'status', 'sent_at'])
                ->map(fn ($campaign) => [
                    'id' => $campaign->id,
                    'subject' => $campaign->subject,
                    'recipient_count' => $campaign->recipient_count,
                    'sent_count' => $campaign->sent_count,
                    'failed_count' => $campaign->failed_count,
                    'status' => $campaign->status,
                    'sent_at' => $campaign->sent_at?->toDateTimeString(),
                ])
                ->all() ?? [],
        ]);
    }
}
