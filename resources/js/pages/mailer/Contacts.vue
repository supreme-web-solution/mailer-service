<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ChevronRight,
    Layers,
    Upload,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type ContactBatchRow = {
    id: number;
    name: string;
    contacts_count: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

const props = defineProps<{
    batches: Paginated<ContactBatchRow>;
    totalContacts: number;
    status?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Contacts', href: '/mailer/contacts' },
        ],
    },
});

const form = useForm<{
    emails_text: string;
    csv_file: File | null;
    batch_name: string;
}>({
    emails_text: '',
    csv_file: null,
    batch_name: '',
});

const importModalOpen = ref(false);

const importContacts = (): void => {
    form.post('/mailer/contacts/import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('emails_text', 'csv_file', 'batch_name');
            importModalOpen.value = false;
        },
    });
};

function batchInitials(name: string) {
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');
}

const batchColors = [
    'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
];

function batchColor(id: number) {
    return batchColors[id % batchColors.length];
}
</script>

<template>
    <Head title="Contacts" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">

        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <Users class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Contacts</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your contact batches and recipient lists.
                    </p>
                </div>
            </div>

            <Dialog v-model:open="importModalOpen">
                <DialogTrigger as-child>
                    <Button class="gap-2 shadow-sm">
                        <Upload class="h-4 w-4" />
                        Import Contacts
                    </Button>
                </DialogTrigger>

                <DialogContent>
                    <form class="space-y-4" @submit.prevent="importContacts">
                        <DialogHeader>
                            <DialogTitle>Import into Batch</DialogTitle>
                            <DialogDescription>
                                Add emails and save them into a named batch.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="batch_name">Batch name</Label>
                            <Input id="batch_name" v-model="form.batch_name" type="text" placeholder="May Newsletter" />
                            <InputError :message="form.errors.batch_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="emails_text">Paste emails</Label>
                            <textarea
                                id="emails_text"
                                v-model="form.emails_text"
                                class="min-h-28 w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                placeholder="one@email.com, two@email.com"
                            />
                            <InputError :message="form.errors.emails_text" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="csv_file">Or upload CSV / TXT</Label>
                            <input
                                id="csv_file"
                                type="file"
                                accept=".csv,.txt"
                                class="rounded-lg border bg-background px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-medium file:text-primary"
                                @change="form.csv_file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <InputError :message="form.errors.csv_file" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="importModalOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing || !form.batch_name" class="gap-2">
                                <Users class="h-4 w-4" />
                                Import to Batch
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Success notice -->
        <div
            v-if="props.status"
            class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
        >
            <span class="h-2 w-2 rounded-full bg-emerald-500" />
            {{ props.status.replaceAll('-', ' ') }}
        </div>

        <!-- Stats cards -->
        <div class="grid gap-4 sm:grid-cols-2">
            <Card class="border-0 shadow-sm ring-1 ring-border/60">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Batches</CardTitle>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <Layers class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold tracking-tight">{{ props.batches.total.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Contact groups created</p>
                </CardContent>
            </Card>

            <Card class="border-0 shadow-sm ring-1 ring-border/60">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Contacts</CardTitle>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Users class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold tracking-tight">{{ props.totalContacts.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Active subscribers across all batches</p>
                </CardContent>
            </Card>
        </div>

        <!-- Batch list -->
        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Batches</h2>

            <!-- Empty state -->
            <div
                v-if="props.batches.data.length === 0"
                class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed bg-card py-20 text-center"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <Layers class="h-6 w-6 text-muted-foreground" />
                </div>
                <div>
                    <p class="font-semibold">No batches yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">Import your first contact list to create a batch.</p>
                </div>
                <Dialog v-model:open="importModalOpen">
                    <DialogTrigger as-child>
                        <Button class="gap-2">
                            <Upload class="h-4 w-4" />
                            Import Contacts
                        </Button>
                    </DialogTrigger>
                </Dialog>
            </div>

            <!-- Batch grid -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="batch in props.batches.data"
                    :key="batch.id"
                    :href="`/mailer/contacts/batches/${batch.id}`"
                    class="group flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm ring-1 ring-border/40 transition-all hover:bg-accent hover:shadow-md hover:ring-primary/30"
                >
                    <!-- Avatar -->
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold"
                        :class="batchColor(batch.id)"
                    >
                        {{ batchInitials(batch.name) }}
                    </div>

                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-sm">{{ batch.name }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ batch.contacts_count.toLocaleString() }} contact{{ batch.contacts_count !== 1 ? 's' : '' }}
                        </p>
                    </div>

                    <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                </Link>
            </div>

            <!-- Pagination -->
            <div
                v-if="props.batches.last_page > 1"
                class="flex items-center justify-between border-t pt-4 text-sm"
            >
                <span class="text-muted-foreground">
                    Page {{ props.batches.current_page }} of {{ props.batches.last_page }}
                </span>
                <div class="flex gap-2">
                    <Link
                        v-if="props.batches.prev_page_url"
                        :href="props.batches.prev_page_url"
                        class="inline-flex h-8 items-center rounded-lg border bg-card px-3 text-xs font-medium shadow-sm transition hover:bg-accent"
                    >
                        ← Previous
                    </Link>
                    <Link
                        v-if="props.batches.next_page_url"
                        :href="props.batches.next_page_url"
                        class="inline-flex h-8 items-center rounded-lg border bg-card px-3 text-xs font-medium shadow-sm transition hover:bg-accent"
                    >
                        Next →
                    </Link>
                </div>
            </div>
        </div>

    </div>
</template>
