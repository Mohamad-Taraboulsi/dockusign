<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FileSignature,
    ArrowLeft,
    Send,
    Ban,
    Clock,
    CheckCircle2,
    XCircle,
    Eye,
    User,
    Download,
    PackageCheck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Document, DocumentStatus, RecipientStatus } from '@/types/esign';
import { STATUS_LABELS, RECIPIENT_COLORS } from '@/types/esign';
import { flattenPdf, downloadBlob } from '@/utils/pdfFlattener';
import axios from 'axios';

const props = defineProps<{
    document: Document;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'eSign', href: '/esign/documents' },
    {
        title: props.document.title,
        href: `/esign/documents/${props.document.id}`,
    },
];

const voiding = ref(false);
const flattening = ref<string | null>(null);
const flattenProgress = ref(0);

function statusVariant(
    status: DocumentStatus | RecipientStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
        case 'signed':
            return 'default';
        case 'declined':
        case 'voided':
            return 'destructive';
        case 'draft':
        case 'pending':
            return 'outline';
        default:
            return 'secondary';
    }
}

function voidDocument() {
    toast('Void this document?', {
        description: 'This action cannot be undone. All recipients will be notified.',
        action: {
            label: 'Void',
            onClick: () => {
                voiding.value = true;
                router.post(
                    `/esign/documents/${props.document.id}/void`,
                    {},
                    {
                        onFinish: () => {
                            voiding.value = false;
                        },
                    },
                );
            },
        },
        cancel: {
            label: 'Cancel',
            onClick: () => {},
        },
    });
}

function formatDate(date: string | null) {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function activityIcon(type: string) {
    switch (type) {
        case 'created':
            return FileSignature;
        case 'sent':
            return Send;
        case 'opened':
            return Eye;
        case 'signed':
            return CheckCircle2;
        case 'completed':
            return CheckCircle2;
        case 'declined':
            return XCircle;
        case 'voided':
            return Ban;
        default:
            return Clock;
    }
}

async function downloadSignedCopy(
    documentId: string,
    recipientId: string,
    recipientName: string | null,
) {
    flattening.value = recipientId;
    flattenProgress.value = 0;

    try {
        const response = await axios.get(
            `/esign/documents/${documentId}/signed/${recipientId}/flatten-data`,
        );
        const { files, document_title } = response.data;

        const allPdfBytes: Uint8Array[] = [];

        for (const file of files) {
            const pdfBytes = await flattenPdf(
                file.pdf_base64,
                file.fields,
                (progress) => {
                    flattenProgress.value = Math.round(
                        (progress / files.length) * 100,
                    );
                },
            );
            allPdfBytes.push(pdfBytes);
        }

        const filename = `${document_title || 'document'}-signed-by-${recipientName || 'recipient'}.pdf`;
        const mergedBytes = new Uint8Array(
            allPdfBytes.reduce((acc, bytes) => acc + bytes.length, 0),
        );
        let offset = 0;
        for (const bytes of allPdfBytes) {
            mergedBytes.set(bytes, offset);
            offset += bytes.length;
        }

        downloadBlob(mergedBytes, filename);
    } catch (error) {
        console.error('Failed to generate PDF:', error);
        toast.error('Failed to generate PDF. Please try again.');
    } finally {
        flattening.value = null;
        flattenProgress.value = 0;
    }
}

async function downloadAllSignedCopies() {
    flattening.value = 'all';
    flattenProgress.value = 0;

    try {
        const signedRecipients = props.document.recipients.filter(
            (r: any) => r.status === 'signed',
        );

        const allPdfBytes: Uint8Array[] = [];

        for (let i = 0; i < signedRecipients.length; i++) {
            const recipient = signedRecipients[i];
            const response = await axios.get(
                `/esign/documents/${props.document.id}/signed/${recipient.id}/flatten-data`,
            );
            const { files, document_title } = response.data;

            for (const file of files) {
                const pdfBytes = await flattenPdf(file.pdf_base64, file.fields);
                allPdfBytes.push(pdfBytes);
            }

            flattenProgress.value = Math.round(
                ((i + 1) / signedRecipients.length) * 100,
            );
        }

        const filename = `${props.document.title || 'documents'}-all-signed.pdf`;
        const mergedBytes = new Uint8Array(
            allPdfBytes.reduce((acc, bytes) => acc + bytes.length, 0),
        );
        let offset = 0;
        for (const bytes of allPdfBytes) {
            mergedBytes.set(bytes, offset);
            offset += bytes.length;
        }

        downloadBlob(mergedBytes, filename);
    } catch (error) {
        console.error('Failed to generate PDF:', error);
        toast.error('Failed to generate PDF. Please try again.');
    } finally {
        flattening.value = null;
        flattenProgress.value = 0;
    }
}
</script>

<template>
    <Head :title="`${document.title} - eSign`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4 md:p-6">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <a
                        href="/esign/documents"
                        class="rounded-md p-1 hover:bg-accent"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </a>
                    <div>
                        <h1 class="text-2xl font-semibold">
                            {{ document.title }}
                        </h1>
                        <p
                            v-if="document.subject"
                            class="text-sm text-muted-foreground"
                        >
                            {{ document.subject }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        :variant="statusVariant(document.status)"
                        class="text-sm"
                    >
                        {{ STATUS_LABELS[document.status] }}
                    </Badge>
                    <a v-if="document.status === 'completed'" href="#">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="flattening === 'all'"
                            @click.prevent="downloadAllSignedCopies"
                        >
                            <PackageCheck class="mr-2 h-4 w-4" />
                            {{
                                flattening === 'all'
                                    ? 'Generating...'
                                    : 'Download All'
                            }}
                        </Button>
                    </a>
                    <Button
                        v-if="
                            !['completed', 'voided', 'draft'].includes(
                                document.status,
                            )
                        "
                        variant="destructive"
                        size="sm"
                        :disabled="voiding"
                        @click="voidDocument"
                    >
                        <Ban class="mr-2 h-4 w-4" />
                        Void
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Files -->
                    <section class="rounded-lg border bg-card">
                        <div class="border-b p-4">
                            <h2 class="font-medium">Documents</h2>
                        </div>
                        <div class="divide-y">
                            <div
                                v-for="file in document.files"
                                :key="file.id"
                                class="flex items-center gap-3 p-4"
                            >
                                <FileSignature
                                    class="h-5 w-5 text-muted-foreground"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ file.original_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ file.page_count }} page{{
                                            file.page_count !== 1 ? 's' : ''
                                        }}
                                    </p>
                                </div>
                                <div class="flex gap-1">
                                    <a
                                        :href="`/esign/documents/${document.id}/pdf/${file.id}`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium hover:bg-accent"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                        View
                                    </a>
                                    <a
                                        :href="`/esign/documents/${document.id}/download/${file.id}`"
                                        class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium hover:bg-accent"
                                    >
                                        <Download class="h-3.5 w-3.5" />
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Recipients -->
                    <section class="rounded-lg border bg-card">
                        <div class="border-b p-4">
                            <h2 class="font-medium">Recipients</h2>
                            <p class="text-sm text-muted-foreground">
                                Signing order:
                                {{
                                    document.signing_order === 'sequential'
                                        ? 'Sequential'
                                        : 'Parallel'
                                }}
                            </p>
                        </div>
                        <div class="divide-y">
                            <div
                                v-for="(recipient, i) in document.recipients"
                                :key="recipient.id"
                                class="flex items-center gap-3 p-4"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                    :style="{
                                        backgroundColor:
                                            RECIPIENT_COLORS[
                                                i % RECIPIENT_COLORS.length
                                            ],
                                    }"
                                >
                                    {{
                                        (recipient.name || recipient.email)
                                            .charAt(0)
                                            .toUpperCase()
                                    }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">
                                        {{ recipient.name || recipient.email }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ recipient.email }} &middot;
                                        {{ recipient.role }}
                                    </p>
                                </div>
                                <Badge
                                    :variant="statusVariant(recipient.status)"
                                    size="sm"
                                >
                                    {{ recipient.status }}
                                </Badge>
                                <span
                                    v-if="recipient.signed_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDate(recipient.signed_at) }}
                                </span>
                                <div
                                    v-if="recipient.status === 'signed'"
                                    class="flex gap-1"
                                >
                                    <a
                                        :href="`/esign/documents/${document.id}/signed/${recipient.id}`"
                                        class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium hover:bg-accent"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                        View
                                    </a>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="flattening === recipient.id"
                                        @click="
                                            downloadSignedCopy(
                                                document.id,
                                                recipient.id,
                                                recipient.name,
                                            )
                                        "
                                    >
                                        <Download class="mr-1 h-3.5 w-3.5" />
                                        {{
                                            flattening === recipient.id
                                                ? 'Generating...'
                                                : 'Download'
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Message -->
                    <section
                        v-if="document.message"
                        class="rounded-lg border bg-card p-4"
                    >
                        <h2 class="mb-2 font-medium">Message</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ document.message }}
                        </p>
                    </section>
                </div>

                <!-- Activity Timeline -->
                <div class="lg:col-span-1">
                    <section class="rounded-lg border bg-card">
                        <div class="border-b p-4">
                            <h2 class="font-medium">Activity</h2>
                        </div>
                        <div class="p-4">
                            <div class="relative">
                                <div
                                    class="absolute top-0 bottom-0 left-3 w-px bg-border"
                                />
                                <div
                                    v-for="activity in document.activities"
                                    :key="activity.id"
                                    class="relative mb-4 flex gap-3 last:mb-0"
                                >
                                    <div
                                        class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-card ring-2 ring-border"
                                    >
                                        <component
                                            :is="activityIcon(activity.type)"
                                            class="h-3 w-3 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="min-w-0 pt-0.5">
                                        <p class="text-sm">
                                            {{ activity.description }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(activity.created_at)
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
