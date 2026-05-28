<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/mailer/RichTextEditor.vue';
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

type TemplateRow = {
    id: number;
    name: string;
    subject: string;
    body: string;
    updated_at: string;
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
    templates: Paginated<TemplateRow>;
    status?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Email templates',
                href: '/mailer/templates',
            },
        ],
    },
});

const editingId = ref<number | null>(null);
const isModalOpen = ref(false);

const form = useForm({
    name: '',
    subject: '',
    body: '',
});

const isEditing = computed(() => editingId.value !== null);

const resetForm = (): void => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const editTemplate = (template: TemplateRow): void => {
    editingId.value = template.id;
    form.name = template.name;
    form.subject = template.subject;
    form.body = template.body;
    isModalOpen.value = true;
};

const submit = (): void => {
    if (isEditing.value && editingId.value !== null) {
        form.patch(`/mailer/templates/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                isModalOpen.value = false;
            },
        });

        return;
    }

    form.post('/mailer/templates', {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            isModalOpen.value = false;
        },
    });
};

const removeTemplate = (id: number): void => {
    form.delete(`/mailer/templates/${id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Email templates" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Email templates"
            description="Build and save reusable templates with the same rich editor experience used in webinar-app."
        />

        <div
            v-if="props.status"
            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            {{ props.status.replaceAll('-', ' ') }}
        </div>

        <Dialog v-model:open="isModalOpen">
            <DialogTrigger as-child>
                <Button @click="resetForm">Create template</Button>
            </DialogTrigger>

            <DialogContent class="max-w-3xl">
                <form class="space-y-4" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ isEditing ? 'Edit template' : 'Create template' }}</DialogTitle>
                        <DialogDescription>
                            Save reusable email templates for your campaigns.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="template-name">Template name</Label>
                        <Input id="template-name" v-model="form.name" placeholder="Welcome campaign" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="template-subject">Subject</Label>
                        <Input id="template-subject" v-model="form.subject" placeholder="Your webinar replay link" />
                        <InputError :message="form.errors.subject" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Body</Label>
                        <RichTextEditor v-model="form.body" />
                        <InputError :message="form.errors.body" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" :disabled="form.processing" @click="isModalOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ isEditing ? 'Update template' : 'Create template' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <div class="rounded-xl border bg-card p-4">
            <h2 class="mb-3 text-sm font-semibold">Saved templates</h2>
            <div v-if="props.templates.data.length === 0" class="text-sm text-muted-foreground">
                No templates yet. Create one above.
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="template in props.templates.data"
                    :key="template.id"
                    class="rounded-lg border px-3 py-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ template.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ template.subject }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button type="button" size="sm" variant="outline" @click="editTemplate(template)">Edit</Button>
                            <Button type="button" size="sm" variant="destructive" @click="removeTemplate(template.id)">Delete</Button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t pt-3 text-sm">
                    <span class="text-muted-foreground">
                        Page {{ props.templates.current_page }} of {{ props.templates.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Link
                            v-if="props.templates.prev_page_url"
                            :href="props.templates.prev_page_url"
                            class="rounded-md border px-2 py-1 hover:bg-muted"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="props.templates.next_page_url"
                            :href="props.templates.next_page_url"
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
