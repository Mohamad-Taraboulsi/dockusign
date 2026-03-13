<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Upload, X, Plus, UserPlus, ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { RecipientRole, SigningOrder } from '@/types/esign';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'eSign', href: '/esign/documents' },
    { title: 'New Document', href: '/esign/documents/create' },
];

const form = useForm<{
    title: string;
    subject: string;
    message: string;
    signing_order: SigningOrder;
    files: File[];
    recipients: { email: string; name: string; role: RecipientRole }[];
}>({
    title: '',
    subject: '',
    message: '',
    signing_order: 'parallel',
    files: [],
    recipients: [{ email: '', name: '', role: 'signer' }],
});

const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

function handleFileDrop(e: DragEvent) {
    dragOver.value = false;
    if (e.dataTransfer?.files) {
        addFiles(Array.from(e.dataTransfer.files));
    }
}

function handleFileSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files) {
        addFiles(Array.from(input.files));
    }
    input.value = '';
}

function addFiles(newFiles: File[]) {
    form.files = [...form.files, ...newFiles];
    if (!form.title && newFiles.length > 0) {
        form.title = newFiles[0].name.replace(/\.[^.]+$/, '');
    }
}

function removeFile(index: number) {
    form.files = form.files.filter((_, i) => i !== index);
}

function addRecipient() {
    form.recipients.push({ email: '', name: '', role: 'signer' });
}

function removeRecipient(index: number) {
    form.recipients.splice(index, 1);
}

function formatFileSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function submit() {
    const formData = new FormData();
    formData.append('title', form.title);
    formData.append('subject', form.subject);
    formData.append('message', form.message);
    formData.append('signing_order', form.signing_order);

    form.files.forEach((file, i) => {
        formData.append(`files[${i}]`, file);
    });

    form.recipients.forEach((r, i) => {
        formData.append(`recipients[${i}][email]`, r.email);
        formData.append(`recipients[${i}][name]`, r.name);
        formData.append(`recipients[${i}][role]`, r.role);
    });

    form.post('/esign/documents', {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="New Document - eSign" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 md:p-6">
            <div class="mb-6 flex items-center gap-3">
                <a href="/esign/documents" class="rounded-md p-1 hover:bg-accent">
                    <ArrowLeft class="h-5 w-5" />
                </a>
                <h1 class="text-2xl font-semibold">New Document</h1>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-8">
                <!-- File Upload -->
                <section class="flex flex-col gap-3">
                    <Label class="text-base font-medium">Upload Documents</Label>
                    <div
                        class="flex min-h-[160px] cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-8 transition-colors"
                        :class="dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-primary/50'"
                        @dragover.prevent="dragOver = true"
                        @dragleave="dragOver = false"
                        @drop.prevent="handleFileDrop"
                        @click="fileInputRef?.click()"
                    >
                        <Upload class="h-8 w-8 text-muted-foreground" />
                        <div class="text-center">
                            <p class="font-medium">Drop files here or click to browse</p>
                            <p class="text-sm text-muted-foreground">PDF, Word, Excel, PowerPoint, or images</p>
                        </div>
                    </div>
                    <input
                        ref="fileInputRef"
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg"
                        class="hidden"
                        @change="handleFileSelect"
                    />

                    <!-- File list -->
                    <div v-if="form.files.length" class="flex flex-col gap-2">
                        <div
                            v-for="(file, index) in form.files"
                            :key="index"
                            class="flex items-center gap-3 rounded-lg border bg-card p-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ file.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatFileSize(file.size) }}</p>
                            </div>
                            <button type="button" class="rounded-md p-1 hover:bg-destructive/10" @click="removeFile(index)">
                                <X class="h-4 w-4 text-destructive" />
                            </button>
                        </div>
                    </div>
                    <p v-if="form.errors.files" class="text-sm text-destructive">{{ form.errors.files }}</p>
                </section>

                <!-- Document Details -->
                <section class="flex flex-col gap-4">
                    <Label class="text-base font-medium">Details</Label>
                    <div class="flex flex-col gap-3">
                        <div>
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="form.title" placeholder="Document title" />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-destructive">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <Label for="subject">Email Subject (optional)</Label>
                            <Input id="subject" v-model="form.subject" placeholder="Please sign this document" />
                        </div>
                        <div>
                            <Label for="message">Message (optional)</Label>
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="3"
                                placeholder="Add a message for your recipients..."
                                class="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            />
                        </div>
                    </div>
                </section>

                <!-- Signing Order -->
                <section class="flex flex-col gap-3">
                    <Label class="text-base font-medium">Signing Order</Label>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border-2 p-4 text-left transition-colors"
                            :class="form.signing_order === 'parallel' ? 'border-primary bg-primary/5' : 'border-transparent bg-muted/50 hover:bg-muted'"
                            @click="form.signing_order = 'parallel'"
                        >
                            <p class="font-medium">Parallel</p>
                            <p class="text-sm text-muted-foreground">All recipients sign at the same time</p>
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border-2 p-4 text-left transition-colors"
                            :class="form.signing_order === 'sequential' ? 'border-primary bg-primary/5' : 'border-transparent bg-muted/50 hover:bg-muted'"
                            @click="form.signing_order = 'sequential'"
                        >
                            <p class="font-medium">Sequential</p>
                            <p class="text-sm text-muted-foreground">Recipients sign one after another in order</p>
                        </button>
                    </div>
                </section>

                <!-- Recipients -->
                <section class="flex flex-col gap-3">
                    <Label class="text-base font-medium">Recipients</Label>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="(recipient, index) in form.recipients"
                            :key="index"
                            class="flex items-start gap-3 rounded-lg border bg-card p-4"
                        >
                            <div
                                class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                :style="{ backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'][index % 8] }"
                            >
                                {{ index + 1 }}
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex gap-2">
                                    <div class="flex-1">
                                        <Input v-model="recipient.name" placeholder="Name" />
                                    </div>
                                    <div class="flex-1">
                                        <Input v-model="recipient.email" type="email" placeholder="Email address" />
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <select
                                        v-model="recipient.role"
                                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    >
                                        <option value="signer">Signer</option>
                                        <option value="cc">CC (copy)</option>
                                    </select>
                                </div>
                            </div>
                            <button
                                v-if="form.recipients.length > 1"
                                type="button"
                                class="mt-1 rounded-md p-1 hover:bg-destructive/10"
                                @click="removeRecipient(index)"
                            >
                                <X class="h-4 w-4 text-destructive" />
                            </button>
                        </div>
                    </div>
                    <Button type="button" variant="outline" size="sm" class="self-start" @click="addRecipient">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Add Recipient
                    </Button>
                    <p v-if="form.errors.recipients" class="text-sm text-destructive">{{ form.errors.recipients }}</p>
                </section>

                <!-- Submit -->
                <div class="flex justify-end gap-3 border-t pt-4">
                    <a href="/esign/documents">
                        <Button type="button" variant="outline">Cancel</Button>
                    </a>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Continue to Editor' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
