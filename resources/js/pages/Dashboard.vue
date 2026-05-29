<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    FileText,
    Mail,
    Send,
    TrendingUp,
    Users,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const props = defineProps<{
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

const page = usePage();
const userName = computed(() => (page.props.auth as any)?.user?.name ?? 'there');

const today = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

function formatDate(dateStr: string | null) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function deliveryRate(campaign: RecentCampaign) {
    if (!campaign.recipient_count) return 0;
    return Math.round((campaign.sent_count / campaign.recipient_count) * 100);
}

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    sent: { label: 'Sent', variant: 'default' },
    sending: { label: 'Sending', variant: 'secondary' },
    failed: { label: 'Failed', variant: 'destructive' },
    draft: { label: 'Draft', variant: 'outline' },
};

function getStatusConfig(status: string) {
    return statusConfig[status] ?? { label: status, variant: 'secondary' as const };
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary/90 to-primary p-6 text-primary-foreground shadow-lg md:p-8">
            <div class="relative z-10">
                <p class="text-sm font-medium opacity-80">{{ today }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">
                    Welcome back, {{ userName }} 👋
                </h1>
                <p class="mt-1 text-sm opacity-75">
                    Here's what's happening with your email campaigns today.
                </p>
            </div>
            <!-- Decorative circles -->
            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10" />
            <div class="absolute -bottom-12 right-16 h-32 w-32 rounded-full bg-white/10" />
            <div class="absolute bottom-4 right-48 h-16 w-16 rounded-full bg-white/5" />
        </div>

        <!-- Stats Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="border-0 shadow-sm ring-1 ring-border/60 transition-shadow hover:shadow-md">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Contacts</CardTitle>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <Users class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold tracking-tight">{{ stats.contacts.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Subscribers in your lists</p>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm ring-1 ring-border/60 transition-shadow hover:shadow-md">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Templates</CardTitle>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                        <FileText class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold tracking-tight">{{ stats.templates.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Email templates created</p>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm ring-1 ring-border/60 transition-shadow hover:shadow-md">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Campaigns</CardTitle>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <Mail class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold tracking-tight">{{ stats.campaigns.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Campaigns launched</p>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm ring-1 ring-border/60 transition-shadow hover:shadow-md">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Emails Sent</CardTitle>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <TrendingUp class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold tracking-tight">{{ stats.sent_total.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Total emails delivered</p>
                </CardContent>
            </Card>
        </div>

        <!-- Quick Actions + Recent Campaigns -->
        <div class="grid gap-6 lg:grid-cols-3">

            <!-- Quick Actions -->
            <div class="flex flex-col gap-3">
                <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Quick Actions</h2>

                <Link
                    href="/mailer/templates"
                    class="group flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm ring-1 ring-border/40 transition-all hover:bg-accent hover:shadow-md hover:ring-primary/30"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                        <FileText class="h-5 w-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm">Manage Templates</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Create & edit email designs</p>
                    </div>
                    <ArrowRight class="h-4 w-4 text-muted-foreground opacity-0 transition-all group-hover:opacity-100 group-hover:translate-x-1" />
                </Link>

                <Link
                    href="/mailer/contacts"
                    class="group flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm ring-1 ring-border/40 transition-all hover:bg-accent hover:shadow-md hover:ring-primary/30"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <Users class="h-5 w-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm">Import Contacts</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Add lists from CSV or text</p>
                    </div>
                    <ArrowRight class="h-4 w-4 text-muted-foreground opacity-0 transition-all group-hover:opacity-100 group-hover:translate-x-1" />
                </Link>

                <Link
                    href="/mailer/send"
                    class="group flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm ring-1 ring-border/40 transition-all hover:bg-accent hover:shadow-md hover:ring-primary/30"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Zap class="h-5 w-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm">Send Campaign</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Dispatch via Resend</p>
                    </div>
                    <ArrowRight class="h-4 w-4 text-muted-foreground opacity-0 transition-all group-hover:opacity-100 group-hover:translate-x-1" />
                </Link>
            </div>

            <!-- Recent Campaigns -->
            <div class="lg:col-span-2 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Recent Campaigns</h2>
                    <Link href="/mailer/send" class="text-xs font-medium text-primary hover:underline">
                        New campaign →
                    </Link>
                </div>

                <Card class="border-0 shadow-sm ring-1 ring-border/60 flex-1">
                    <CardContent class="p-0">
                        <!-- Empty state -->
                        <div v-if="recentCampaigns.length === 0" class="flex flex-col items-center justify-center gap-3 py-14 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                                <Send class="h-6 w-6 text-muted-foreground" />
                            </div>
                            <div>
                                <p class="font-medium text-sm">No campaigns yet</p>
                                <p class="text-xs text-muted-foreground mt-1">Send your first campaign to see it here.</p>
                            </div>
                            <Link
                                href="/mailer/send"
                                class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90"
                            >
                                <Zap class="h-3.5 w-3.5" />
                                Send a campaign
                            </Link>
                        </div>

                        <!-- Campaign list -->
                        <div v-else class="divide-y divide-border/60">
                            <div
                                v-for="campaign in recentCampaigns"
                                :key="campaign.id"
                                class="flex items-center gap-4 px-5 py-4 transition hover:bg-muted/30"
                            >
                                <!-- Status icon -->
                                <div class="shrink-0">
                                    <CheckCircle2
                                        v-if="campaign.status === 'sent'"
                                        class="h-5 w-5 text-emerald-500"
                                    />
                                    <Clock
                                        v-else-if="campaign.status === 'sending'"
                                        class="h-5 w-5 text-amber-500"
                                    />
                                    <XCircle
                                        v-else-if="campaign.status === 'failed'"
                                        class="h-5 w-5 text-destructive"
                                    />
                                    <Mail
                                        v-else
                                        class="h-5 w-5 text-muted-foreground"
                                    />
                                </div>

                                <!-- Subject & meta -->
                                <div class="flex-1 min-w-0">
                                    <p class="truncate text-sm font-medium">{{ campaign.subject }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ campaign.sent_count }}/{{ campaign.recipient_count }} sent
                                        <span v-if="campaign.failed_count > 0" class="text-destructive">
                                            · {{ campaign.failed_count }} failed
                                        </span>
                                        <span class="mx-1">·</span>
                                        {{ formatDate(campaign.sent_at) }}
                                    </p>
                                </div>

                                <!-- Delivery rate pill -->
                                <div class="hidden shrink-0 flex-col items-end sm:flex">
                                    <span class="text-sm font-semibold tabular-nums">
                                        {{ deliveryRate(campaign) }}%
                                    </span>
                                    <span class="text-[10px] text-muted-foreground">delivery</span>
                                </div>

                                <!-- Status badge -->
                                <Badge
                                    :variant="getStatusConfig(campaign.status).variant"
                                    class="shrink-0 capitalize"
                                >
                                    {{ getStatusConfig(campaign.status).label }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

        </div>
    </div>
</template>
