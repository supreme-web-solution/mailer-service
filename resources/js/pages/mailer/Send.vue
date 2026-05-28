<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type TemplateOption = {
    id: number;
    name: string;
    subject: string;
};

type ContactOption = {
    id: number;
    email: string;
};

type BatchOption = {
    id: number;
    name: string;
    contacts_count: number;
};

type LastCampaign = {
    id: number;
    subject: string;
    recipient_count: number;
    sent_count: number;
    failed_count: number;
    status: string;
    sent_at: string | null;
} | null;

const props = defineProps<{
    templates: TemplateOption[];
    contacts: ContactOption[];
    batches: BatchOption[];
    lastCampaign: LastCampaign;
    status?: string;
}>();

const statusMessage = (): string => {
    if (props.status === 'campaign-queued') {
        return 'Campaign queued. Queue workers will process sends in background chunks.';
    }

    return props.status ?? '';
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Send campaign',
                href: '/mailer/send',
            },
        ],
    },
});

const form = useForm({
    mail_template_id: '',
    recipient_mode: 'all',
    recipient_ids: [] as number[],
    recipient_batch_id: '',
});

const toggleRecipient = (id: number): void => {
    if (form.recipient_ids.includes(id)) {
        form.recipient_ids = form.recipient_ids.filter((item) => item !== id);

        return;
    }

    form.recipient_ids = [...form.recipient_ids, id];
};

const submit = (): void => {
    form.post('/mailer/send', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Send campaign" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Send campaign"
            description="Pick a template and send to all contacts, selected contacts, or a saved batch."
        />

        <div
            v-if="props.status"
            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            {{ statusMessage() }}
        </div>

        <form class="space-y-4 rounded-xl border bg-card p-4" @submit.prevent="submit">
            <p class="text-xs text-muted-foreground">
                High-volume sends are queued. Keep a queue worker running for delivery.
            </p>
            <div class="grid gap-2">
                <Label for="mail_template_id">Template</Label>
                <select
                    id="mail_template_id"
                    v-model="form.mail_template_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Select template</option>
                    <option v-for="template in props.templates" :key="template.id" :value="template.id">
                        {{ template.name }} - {{ template.subject }}
                    </option>
                </select>
                <InputError :message="form.errors.mail_template_id" />
            </div>

            <div class="grid gap-2">
                <Label>Recipients</Label>
                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.recipient_mode" type="radio" value="all" />
                        All contacts ({{ props.contacts.length }})
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.recipient_mode" type="radio" value="selected" />
                        Selected contacts
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.recipient_mode" type="radio" value="batch" />
                        Saved batch
                    </label>
                </div>
                <InputError :message="form.errors.recipient_mode" />
            </div>

            <div
                v-if="form.recipient_mode === 'selected'"
                class="max-h-56 space-y-2 overflow-y-auto rounded-md border p-3"
            >
                <label
                    v-for="contact in props.contacts"
                    :key="contact.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        :checked="form.recipient_ids.includes(contact.id)"
                        @change="toggleRecipient(contact.id)"
                    />
                    {{ contact.email }}
                </label>
                <InputError :message="form.errors.recipient_ids" />
            </div>

            <div v-if="form.recipient_mode === 'batch'" class="grid gap-2">
                <Label for="recipient_batch_id">Choose batch</Label>
                <select
                    id="recipient_batch_id"
                    v-model="form.recipient_batch_id"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Select batch</option>
                    <option v-for="batch in props.batches" :key="batch.id" :value="batch.id">
                        {{ batch.name }} ({{ batch.contacts_count }})
                    </option>
                </select>
                <InputError :message="form.errors.recipient_batch_id" />
            </div>

            <Button type="submit" :disabled="form.processing || props.templates.length === 0 || props.contacts.length === 0">
                Send now
            </Button>
        </form>

        <div v-if="props.lastCampaign" class="rounded-xl border bg-card p-4">
            <h2 class="mb-2 text-sm font-semibold">Last campaign</h2>
            <p class="text-sm">{{ props.lastCampaign.subject }}</p>
            <p class="text-xs text-muted-foreground">
                Sent: {{ props.lastCampaign.sent_count }} / {{ props.lastCampaign.recipient_count }},
                Failed: {{ props.lastCampaign.failed_count }},
                Status: {{ props.lastCampaign.status }}
            </p>
        </div>
    </div>
</template>
