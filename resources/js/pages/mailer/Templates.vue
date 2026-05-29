<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    FileText,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/mailer/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
            { title: 'Dashboard', href: dashboard() },
            { title: 'Email Templates', href: '/mailer/templates' },
        ],
    },
});

const editingId = ref<number | null>(null);
const isModalOpen = ref(false);
const deleteConfirmId = ref<number | null>(null);

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
    form.delete(`/mailer/templates/${id}`, { preserveScroll: true });
    deleteConfirmId.value = null;
};

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function stripHtml(html: string) {
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120);
}
</script>

<template>
    <Head title="Email Templates" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">

        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                    <FileText class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Email Templates</h1>
                    <p class="text-sm text-muted-foreground">
                        Build and save reusable templates for your campaigns.
                    </p>
                </div>
            </div>

            <Dialog v-model:open="isModalOpen">
                <DialogTrigger as-child>
                    <Button class="gap-2 shadow-sm" @click="resetForm">
                        <Plus class="h-4 w-4" />
                        New Template
                    </Button>
                </DialogTrigger>

                <DialogContent class="max-w-3xl">
                    <form class="space-y-4" @submit.prevent="submit">
                        <DialogHeader>
                            <DialogTitle>{{ isEditing ? 'Edit Template' : 'Create Template' }}</DialogTitle>
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
                            <Label for="template-subject">Subject line</Label>
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
                                {{ isEditing ? 'Update Template' : 'Create Template' }}
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

        <!-- Template count -->
        <p v-if="props.templates.total > 0" class="text-sm text-muted-foreground">
            {{ props.templates.total }} template{{ props.templates.total !== 1 ? 's' : '' }} saved
        </p>

        <!-- Empty state -->
        <div v-if="props.templates.data.length === 0" class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed bg-card py-20 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                <FileText class="h-6 w-6 text-muted-foreground" />
            </div>
            <div>
                <p class="font-semibold">No templates yet</p>
                <p class="mt-1 text-sm text-muted-foreground">Create your first email template to get started.</p>
            </div>
            <Dialog v-model:open="isModalOpen">
                <DialogTrigger as-child>
                    <Button class="gap-2" @click="resetForm">
                        <Plus class="h-4 w-4" />
                        Create your first template
                    </Button>
                </DialogTrigger>
            </Dialog>
        </div>

        <!-- Template grid -->
        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="template in props.templates.data"
                :key="template.id"
                class="group border-0 shadow-sm ring-1 ring-border/60 transition-all hover:shadow-md hover:ring-primary/30"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                                <FileText class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-sm">{{ template.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ template.subject }}</p>
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="flex flex-col gap-4 pt-0">
                    <!-- Body preview -->
                    <p class="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                        {{ stripHtml(template.body) || 'No body content.' }}
                    </p>

                    <!-- Footer row -->
                    <div class="flex items-center justify-between border-t pt-3">
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Calendar class="h-3 w-3" />
                            {{ formatDate(template.updated_at) }}
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-7 gap-1.5 px-2.5 text-xs"
                                @click="editTemplate(template)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                Edit
                            </Button>

                            <!-- Delete confirm inline -->
                            <template v-if="deleteConfirmId === template.id">
                                <span class="text-xs text-muted-foreground">Sure?</span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    class="h-7 px-2.5 text-xs"
                                    :disabled="form.processing"
                                    @click="removeTemplate(template.id)"
                                >
                                    Yes, delete
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    class="h-7 px-2 text-xs"
                                    @click="deleteConfirmId = null"
                                >
                                    No
                                </Button>
                            </template>
                            <Button
                                v-else
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                @click="deleteConfirmId = template.id"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Pagination -->
        <div
            v-if="props.templates.last_page > 1"
            class="flex items-center justify-between border-t pt-4 text-sm"
        >
            <span class="text-muted-foreground">
                Page {{ props.templates.current_page }} of {{ props.templates.last_page }}
            </span>
            <div class="flex gap-2">
                <Link
                    v-if="props.templates.prev_page_url"
                    :href="props.templates.prev_page_url"
                    class="inline-flex h-8 items-center gap-1 rounded-lg border bg-card px-3 text-xs font-medium shadow-sm transition hover:bg-accent"
                >
                    ← Previous
                </Link>
                <Link
                    v-if="props.templates.next_page_url"
                    :href="props.templates.next_page_url"
                    class="inline-flex h-8 items-center gap-1 rounded-lg border bg-card px-3 text-xs font-medium shadow-sm transition hover:bg-accent"
                >
                    Next →
                </Link>
            </div>
        </div>

    </div>
</template>
