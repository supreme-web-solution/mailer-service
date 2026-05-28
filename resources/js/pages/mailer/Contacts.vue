<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Layers, Upload, ChevronRight, Users } from 'lucide-vue-next';
import { ref } from 'vue';

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
            { title: 'Batches', href: '/mailer/contacts' },
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
</script>

<template>
    <Head title="Batches" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading title="Batches" description="Only batches are listed here. Open a batch to send emails." />

        <Dialog v-model:open="importModalOpen">
            <DialogTrigger as-child>
                <Button class="w-fit">
                    <Upload class="size-4" />
                    Import into batch
                </Button>
            </DialogTrigger>

            <DialogContent>
                <form class="space-y-4" @submit.prevent="importContacts">
                    <DialogHeader>
                        <DialogTitle>Import into batch</DialogTitle>
                        <DialogDescription>
                            Add emails and save them into a specific batch.
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
                            class="min-h-28 rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="one@email.com, two@email.com"
                        />
                        <InputError :message="form.errors.emails_text" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="csv_file">Or upload CSV/TXT</Label>
                        <input
                            id="csv_file"
                            type="file"
                            accept=".csv,.txt"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                            @change="form.csv_file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <InputError :message="form.errors.csv_file" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="importModalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing || !form.batch_name">
                            <Users class="size-4" />
                            Import to batch
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Batches</p>
                <p class="mt-2 text-2xl font-semibold">{{ props.batches.total }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Total active contacts</p>
                <p class="mt-2 text-2xl font-semibold">{{ props.totalContacts }}</p>
            </div>
        </div>

        <div v-if="props.status" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ props.status.replaceAll('-', ' ') }}
        </div>

        <div class="rounded-xl border bg-card p-4">
            <div class="mb-3 flex items-center gap-2">
                <Layers class="size-4 text-primary" />
                <h2 class="text-sm font-semibold">Batch list</h2>
            </div>

            <div v-if="props.batches.data.length === 0" class="text-sm text-muted-foreground">No batches yet.</div>
            <div v-else class="space-y-2">
                <Link
                    v-for="batch in props.batches.data"
                    :key="batch.id"
                    :href="`/mailer/contacts/batches/${batch.id}`"
                    class="flex items-center justify-between rounded-lg border px-3 py-2 transition-colors hover:bg-muted/40"
                >
                    <div>
                        <p class="text-sm font-medium">{{ batch.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ batch.contacts_count }} contacts</p>
                    </div>
                    <ChevronRight class="size-4 text-muted-foreground" />
                </Link>
                <div class="flex items-center justify-between border-t pt-3 text-sm">
                    <span class="text-muted-foreground">
                        Page {{ props.batches.current_page }} of {{ props.batches.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Link
                            v-if="props.batches.prev_page_url"
                            :href="props.batches.prev_page_url"
                            class="rounded-md border px-2 py-1 hover:bg-muted"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="props.batches.next_page_url"
                            :href="props.batches.next_page_url"
                            class="rounded-md border px-2 py-1 hover:bg-muted"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
