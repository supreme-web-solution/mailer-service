<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

type Stats = {
    contacts: number;
    templates: number;
    campaigns: number;
    sent_total: number;
};

type RecentCampaign = {
    id: number;
    subject: string;
    recipient_count: number;
    sent_count: number;
    failed_count: number;
    status: string;
    sent_at: string | null;
};

defineProps<{
    stats: Stats;
    recentCampaigns: RecentCampaign[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Contacts</p>
                <p class="text-2xl font-semibold">{{ stats.contacts }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Templates</p>
                <p class="text-2xl font-semibold">{{ stats.templates }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Campaigns</p>
                <p class="text-2xl font-semibold">{{ stats.campaigns }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Total sent</p>
                <p class="text-2xl font-semibold">{{ stats.sent_total }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Link href="/mailer/templates" class="rounded-xl border bg-card p-4 transition hover:bg-muted/40">
                <p class="font-medium">Manage templates</p>
                <p class="text-sm text-muted-foreground">Create and edit email templates with rich text editor.</p>
            </Link>
            <Link href="/mailer/contacts" class="rounded-xl border bg-card p-4 transition hover:bg-muted/40">
                <p class="font-medium">Import contacts</p>
                <p class="text-sm text-muted-foreground">Add recipient lists from text or CSV files.</p>
            </Link>
            <Link href="/mailer/send" class="rounded-xl border bg-card p-4 transition hover:bg-muted/40">
                <p class="font-medium">Send campaign</p>
                <p class="text-sm text-muted-foreground">Pick a template and send through Resend.</p>
            </Link>
        </div>

        <div class="rounded-xl border bg-card p-4">
            <h2 class="mb-3 text-sm font-semibold">Recent campaigns</h2>
            <div v-if="recentCampaigns.length === 0" class="text-sm text-muted-foreground">
                No campaigns yet.
            </div>
            <div v-else class="space-y-2">
                <div
                    v-for="campaign in recentCampaigns"
                    :key="campaign.id"
                    class="rounded-lg border px-3 py-2"
                >
                    <p class="text-sm font-medium">{{ campaign.subject }}</p>
                    <p class="text-xs text-muted-foreground">
                        Sent {{ campaign.sent_count }}/{{ campaign.recipient_count }},
                        failed {{ campaign.failed_count }} ({{ campaign.status }})
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
