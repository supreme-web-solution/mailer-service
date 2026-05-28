<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
            { title: 'Batches', href: '/mailer/contacts' },
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

const allUnsubscribedSelected = computed(() =>
    props.unsubscribed.data.length > 0
    && selectedSuppressionIds.value.length === props.unsubscribed.data.length
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
</script>

<template>
    <Head :title="`Batch - ${props.batch.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                :title="props.batch.name"
                :description="`Send directly to this batch (${props.batch.contacts_count} contacts).`"
            />
            <Link href="/mailer/contacts" class="text-sm text-muted-foreground hover:text-foreground">Back to batches</Link>
        </div>

        <div v-if="props.status" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ props.status.replaceAll('-', ' ') }}
        </div>

        <form class="space-y-4 rounded-xl border bg-card p-4" @submit.prevent="submit">
            <h2 class="text-sm font-semibold">Send to this batch</h2>
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

            <input v-model="form.recipient_mode" type="hidden" />
            <input v-model="form.recipient_batch_id" type="hidden" />

            <Button type="submit" :disabled="form.processing || props.templates.length === 0 || props.contacts.data.length === 0">
                Send to {{ props.batch.name }}
            </Button>
            <InputError :message="form.errors.recipient_batch_id" />
        </form>

        <div class="flex gap-2 rounded-lg border bg-muted p-1 w-fit">
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm"
                :class="activeTab === 'contacts' ? 'bg-background shadow-sm' : 'text-muted-foreground'"
                @click="activeTab = 'contacts'"
            >
                Contacts ({{ props.contacts.total }})
            </button>
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-sm"
                :class="activeTab === 'unsubscribed' ? 'bg-background shadow-sm' : 'text-muted-foreground'"
                @click="activeTab = 'unsubscribed'"
            >
                Unsubscribed ({{ props.unsubscribed.total }})
            </button>
        </div>

        <div v-if="activeTab === 'contacts'" class="rounded-xl border bg-card p-4">
            <h2 class="mb-3 text-sm font-semibold">Contacts in this batch ({{ props.contacts.total }})</h2>
            <div v-if="props.contacts.data.length === 0" class="text-sm text-muted-foreground">
                This batch has no active contacts.
            </div>
            <div v-else class="space-y-2">
                <div
                    v-for="contact in props.contacts.data"
                    :key="contact.id"
                    class="rounded-lg border px-3 py-2"
                >
                    <p class="text-sm font-medium">{{ contact.email }}</p>
                    <p v-if="contact.name" class="text-xs text-muted-foreground">{{ contact.name }}</p>
                </div>
                <div class="flex items-center justify-between border-t pt-3 text-sm">
                    <span class="text-muted-foreground">
                        Page {{ props.contacts.current_page }} of {{ props.contacts.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Link
                            v-if="props.contacts.prev_page_url"
                            :href="props.contacts.prev_page_url"
                            class="rounded-md border px-2 py-1 hover:bg-muted"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="props.contacts.next_page_url"
                            :href="props.contacts.next_page_url"
                            class="rounded-md border px-2 py-1 hover:bg-muted"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl border bg-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">Unsubscribed in this batch ({{ props.unsubscribed.total }})</h2>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    :disabled="selectedSuppressionIds.length === 0 || bulkDeleteForm.processing"
                    @click="bulkRemoveSuppressions"
                >
                    Delete selected
                </Button>
            </div>

            <div v-if="props.unsubscribed.data.length === 0" class="text-sm text-muted-foreground">
                No unsubscribed emails in this batch.
            </div>
            <div v-else class="space-y-2">
                <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                    <input
                        type="checkbox"
                        :checked="allUnsubscribedSelected"
                        @change="toggleAllUnsubscribed"
                    />
                    Select all on this page
                </label>

                <div
                    v-for="item in props.unsubscribed.data"
                    :key="item.id"
                    class="flex items-center justify-between rounded-lg border px-3 py-2"
                >
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :checked="selectedSuppressionIds.includes(item.id)"
                            @change="toggleSuppression(item.id)"
                        />
                        <div>
                            <p class="text-sm font-medium">{{ item.email }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.reason }} <span v-if="item.created_at">- {{ item.created_at }}</span>
                            </p>
                        </div>
                    </label>

                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="deleteForm.processing"
                        @click="removeSuppression(item.id)"
                    >
                        Delete
                    </Button>
                </div>

                <div class="flex items-center justify-between border-t pt-3 text-sm">
                    <span class="text-muted-foreground">
                        Page {{ props.unsubscribed.current_page }} of {{ props.unsubscribed.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Link
                            v-if="props.unsubscribed.prev_page_url"
                            :href="props.unsubscribed.prev_page_url"
                            class="rounded-md border px-2 py-1 hover:bg-muted"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="props.unsubscribed.next_page_url"
                            :href="props.unsubscribed.next_page_url"
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
