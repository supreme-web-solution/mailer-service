<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Mail,
    Send,
    Trash2,
    UserMinus,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type TemplateOption = {
    id: number;
    name: string;
    subject: string;
};

type Batch = {
    id: number;
    name: string;
    contacts_count: number;
};

type BatchContact = {
    id: number;
    name: string | null;
    email: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type BatchSuppression = {
    id: number;
    email: string;
    reason: string;
    created_at: string | null;
};

const props = defineProps<{
    batch: Batch;
    contacts: Paginated<BatchContact>;
    unsubscribed: Paginated<BatchSuppression>;
    templates: TemplateOption[];
    status?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Contacts', href: '/mailer/contacts' },
            { title: 'Batch', href: '#' },
        ],
    },
});

const form = useForm({
    mail_template_id: '',
    recipient_mode: 'batch',
    recipient_batch_id: String(props.batch.id),
});

const submit = (): void => {
    form.post('/mailer/send', { preserveScroll: true });
};

const activeTab = ref<'contacts' | 'unsubscribed'>('contacts');
const deleteForm = useForm({});
const bulkDeleteForm = useForm<{ suppression_ids: number[] }>({
    suppression_ids: [],
});
const selectedSuppressionIds = ref<number[]>([]);

const toggleSuppression = (id: number): void => {
    if (selectedSuppressionIds.value.includes(id)) {
        selectedSuppressionIds.value = selectedSuppressionIds.value.filter((item) => item !== id);
        return;
    }
    selectedSuppressionIds.value = [...selectedSuppressionIds.value, id];
};

const allUnsubscribedSelected = computed(
    () =>
        props.unsubscribed.data.length > 0 &&
        selectedSuppressionIds.value.length === props.unsubscribed.data.length,
);

const toggleAllUnsubscribed = (): void => {
    if (allUnsubscribedSelected.value) {
        selectedSuppressionIds.value = [];
        return;
    }
    selectedSuppressionIds.value = props.unsubscribed.data.map((item) => item.id);
};

const removeSuppression = (id: number): void => {
    deleteForm.delete(`/mailer/contacts/batches/${props.batch.id}/unsubscribed/${id}`, {
        preserveScroll: true,
    });
};

const bulkRemoveSuppressions = (): void => {
    bulkDeleteForm.suppression_ids = [...selectedSuppressionIds.value];
    bulkDeleteForm.delete(`/mailer/contacts/batches/${props.batch.id}/unsubscribed`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedSuppressionIds.value = [];
            bulkDeleteForm.reset('suppression_ids');
        },
    });
};

function emailInitials(email: string) {
    return email.slice(0, 2).toUpperCase();
}

function formatDate(dateStr: string | null) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const avatarColors = [
    'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
];

function avatarColor(id: number) {
    return avatarColors[id % avatarColors.length];
}
</script>

<template>
    <Head :title="`${props.batch.name} — Batch`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">

        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <Users class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">{{ props.batch.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ props.batch.contacts_count.toLocaleString() }} active contact{{ props.batch.contacts_count !== 1 ? 's' : '' }} in this batch
                    </p>
                </div>
            </div>
            <Link
                href="/mailer/contacts"
                class="inline-flex items-center gap-1.5 self-start rounded-lg border bg-card px-3 py-2 text-sm font-medium shadow-sm transition hover:bg-accent"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Back to batches
            </Link>
        </div>

        <!-- Success notice -->
        <div
            v-if="props.status"
            class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
        >
            <span class="h-2 w-2 rounded-full bg-emerald-500" />
            {{ props.status.replaceAll('-', ' ') }}
        </div>

        <!-- Main grid: send form + stats -->
        <div class="grid gap-4 lg:grid-cols-3">

            <!-- Send Campaign Card -->
            <Card class="lg:col-span-2 border-0 shadow-sm ring-1 ring-border/60">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Send class="h-4 w-4" />
                        </div>
                        <CardTitle class="text-sm font-semibold">Send Campaign to this Batch</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="mail_template_id">Choose a template</Label>
                            <div class="relative">
                                <select
                                    id="mail_template_id"
                                    v-model="form.mail_template_id"
                                    class="h-10 w-full appearance-none rounded-lg border bg-background px-3 pr-8 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                >
                                    <option value="">— Select a template —</option>
                                    <option
                                        v-for="template in props.templates"
                                        :key="template.id"
                                        :value="template.id"
                                    >
                                        {{ template.name }} — {{ template.subject }}
                                    </option>
                                </select>
                                <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                            <InputError :message="form.errors.mail_template_id" />
                        </div>

                        <input v-model="form.recipient_mode" type="hidden" />
                        <input v-model="form.recipient_batch_id" type="hidden" />

                        <div
                            v-if="props.templates.length === 0"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
                        >
                            No templates available.
                            <Link href="/mailer/templates" class="font-semibold underline">Create one first.</Link>
                        </div>
                        <div
                            v-else-if="props.contacts.total === 0"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
                        >
                            This batch has no active contacts.
                        </div>

                        <Button
                            type="submit"
                            class="gap-2 shadow-sm"
                            :disabled="form.processing || props.templates.length === 0 || props.contacts.total === 0 || !form.mail_template_id"
                        >
                            <Send class="h-4 w-4" />
                            Send to {{ props.batch.name }}
                        </Button>
                        <InputError :message="form.errors.recipient_batch_id" />
                    </form>
                </CardContent>
            </Card>

            <!-- Stat cards column -->
            <div class="flex flex-col gap-4">
                <Card class="border-0 shadow-sm ring-1 ring-border/60">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Active Contacts</CardTitle>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <Users class="h-4 w-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold tracking-tight">{{ props.contacts.total.toLocaleString() }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Will receive this campaign</p>
                    </CardContent>
                </Card>

                <Card class="border-0 shadow-sm ring-1 ring-border/60">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Unsubscribed</CardTitle>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <UserMinus class="h-4 w-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold tracking-tight">{{ props.unsubscribed.total.toLocaleString() }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Opted out from this batch</p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-xl border bg-muted p-1 w-fit shadow-sm">
            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all"
                :class="activeTab === 'contacts'
                    ? 'bg-background shadow-sm text-foreground'
                    : 'text-muted-foreground hover:text-foreground'"
                @click="activeTab = 'contacts'"
            >
                <Users class="h-3.5 w-3.5" />
                Contacts
                <span
                    class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
                    :class="activeTab === 'contacts' ? 'bg-primary/10 text-primary' : 'bg-muted-foreground/20 text-muted-foreground'"
                >
                    {{ props.contacts.total }}
                </span>
            </button>
            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all"
                :class="activeTab === 'unsubscribed'
                    ? 'bg-background shadow-sm text-foreground'
                    : 'text-muted-foreground hover:text-foreground'"
                @click="activeTab = 'unsubscribed'"
            >
                <UserMinus class="h-3.5 w-3.5" />
                Unsubscribed
                <span
                    class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
                    :class="activeTab === 'unsubscribed' ? 'bg-rose-500/10 text-rose-600' : 'bg-muted-foreground/20 text-muted-foreground'"
                >
                    {{ props.unsubscribed.total }}
                </span>
            </button>
        </div>

        <!-- Contacts Tab -->
        <div v-if="activeTab === 'contacts'">
            <!-- Empty -->
            <div
                v-if="props.contacts.data.length === 0"
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed bg-card py-16 text-center"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                    <Users class="h-5 w-5 text-muted-foreground" />
                </div>
                <div>
                    <p class="font-semibold text-sm">No active contacts</p>
                    <p class="text-xs text-muted-foreground mt-1">This batch has no contacts yet.</p>
                </div>
            </div>

            <!-- List -->
            <Card v-else class="border-0 shadow-sm ring-1 ring-border/60">
                <CardContent class="p-0">
                    <div class="divide-y divide-border/60">
                        <div
                            v-for="contact in props.contacts.data"
                            :key="contact.id"
                            class="flex items-center gap-3 px-5 py-3 transition hover:bg-muted/30"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :class="avatarColor(contact.id)"
                            >
                                {{ emailInitials(contact.email) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ contact.email }}</p>
                                <p v-if="contact.name" class="text-xs text-muted-foreground">{{ contact.name }}</p>
                            </div>
                            <Mail class="h-3.5 w-3.5 shrink-0 text-muted-foreground/50" />
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="props.contacts.last_page > 1"
                        class="flex items-center justify-between border-t px-5 py-3 text-sm"
                    >
                        <span class="text-muted-foreground">
                            Page {{ props.contacts.current_page }} of {{ props.contacts.last_page }}
                        </span>
                        <div class="flex gap-2">
                            <Link
                                v-if="props.contacts.prev_page_url"
                                :href="props.contacts.prev_page_url"
                                class="inline-flex h-7 items-center rounded-lg border bg-card px-3 text-xs font-medium transition hover:bg-accent"
                            >
                                ← Prev
                            </Link>
                            <Link
                                v-if="props.contacts.next_page_url"
                                :href="props.contacts.next_page_url"
                                class="inline-flex h-7 items-center rounded-lg border bg-card px-3 text-xs font-medium transition hover:bg-accent"
                            >
                                Next →
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Unsubscribed Tab -->
        <div v-else>
            <!-- Empty -->
            <div
                v-if="props.unsubscribed.data.length === 0"
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed bg-card py-16 text-center"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                    <UserMinus class="h-5 w-5 text-muted-foreground" />
                </div>
                <div>
                    <p class="font-semibold text-sm">No unsubscribes</p>
                    <p class="text-xs text-muted-foreground mt-1">Nobody has opted out from this batch.</p>
                </div>
            </div>

            <!-- List -->
            <Card v-else class="border-0 shadow-sm ring-1 ring-border/60">
                <CardContent class="p-0">
                    <!-- Toolbar -->
                    <div class="flex items-center justify-between border-b px-5 py-3">
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium">
                            <input
                                type="checkbox"
                                :checked="allUnsubscribedSelected"
                                class="h-4 w-4 rounded border-border accent-primary"
                                @change="toggleAllUnsubscribed"
                            />
                            <span class="text-muted-foreground text-xs">
                                {{ selectedSuppressionIds.length > 0
                                    ? `${selectedSuppressionIds.length} selected`
                                    : 'Select all on this page' }}
                            </span>
                        </label>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            class="h-7 gap-1.5 px-3 text-xs"
                            :disabled="selectedSuppressionIds.length === 0 || bulkDeleteForm.processing"
                            @click="bulkRemoveSuppressions"
                        >
                            <Trash2 class="h-3 w-3" />
                            Delete selected
                        </Button>
                    </div>

                    <!-- Rows -->
                    <div class="divide-y divide-border/60">
                        <div
                            v-for="item in props.unsubscribed.data"
                            :key="item.id"
                            class="flex items-center gap-3 px-5 py-3 transition hover:bg-muted/30"
                            :class="{ 'bg-rose-50/40 dark:bg-rose-950/10': selectedSuppressionIds.includes(item.id) }"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedSuppressionIds.includes(item.id)"
                                class="h-4 w-4 shrink-0 rounded border-border accent-primary"
                                @change="toggleSuppression(item.id)"
                            />
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-500/10 text-xs font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ emailInitials(item.email) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ item.email }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    <span class="capitalize">{{ item.reason }}</span>
                                    <span v-if="item.created_at"> · {{ formatDate(item.created_at) }}</span>
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 shrink-0 p-0 text-muted-foreground hover:text-destructive"
                                :disabled="deleteForm.processing"
                                @click="removeSuppression(item.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="props.unsubscribed.last_page > 1"
                        class="flex items-center justify-between border-t px-5 py-3 text-sm"
                    >
                        <span class="text-muted-foreground">
                            Page {{ props.unsubscribed.current_page }} of {{ props.unsubscribed.last_page }}
                        </span>
                        <div class="flex gap-2">
                            <Link
                                v-if="props.unsubscribed.prev_page_url"
                                :href="props.unsubscribed.prev_page_url"
                                class="inline-flex h-7 items-center rounded-lg border bg-card px-3 text-xs font-medium transition hover:bg-accent"
                            >
                                ← Prev
                            </Link>
                            <Link
                                v-if="props.unsubscribed.next_page_url"
                                :href="props.unsubscribed.next_page_url"
                                class="inline-flex h-7 items-center rounded-lg border bg-card px-3 text-xs font-medium transition hover:bg-accent"
                            >
                                Next →
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

    </div>
</template>
