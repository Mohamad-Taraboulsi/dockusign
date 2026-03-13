<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';
import * as pdfjsLib from 'pdfjs-dist';
import { ref, computed, onMounted, nextTick, markRaw } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    Document,
    DocumentRecipient,
    DocumentField,
    DocumentFile,
} from '@/types/esign';
import { flattenPdf, downloadBlob } from '@/utils/pdfFlattener';

try {
    pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
        'pdfjs-dist/build/pdf.worker.mjs',
        import.meta.url,
    ).href;
} catch {
    pdfjsLib.GlobalWorkerOptions.workerSrc = '';
}

const props = defineProps<{
    document: Document;
    recipient: DocumentRecipient;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'eSign', href: '/esign/documents' },
    {
        title: props.document.title,
        href: `/esign/documents/${props.document.id}`,
    },
    {
        title: `Signed by ${props.recipient.name || props.recipient.email}`,
        href: '#',
    },
];

const loadingPdf = ref(true);
const downloading = ref(false);
const filePagesMap = ref<
    Record<number, { pageNum: number; width: number; height: number }[]>
>({});
const pdfDocs = ref<Record<number, any>>({});
const canvasRefs = ref<Record<string, HTMLCanvasElement>>({});
const zoom = ref(1);

const fields = computed(() => props.document.fields ?? []);

function canvasKey(fileId: number, pageNum: number): string {
    return `${fileId}-${pageNum}`;
}

function getPagesForFile(file: DocumentFile) {
    return filePagesMap.value[file.id] ?? generatePlaceholderPages(file);
}

function generatePlaceholderPages(file: DocumentFile) {
    return Array.from({ length: file.page_count || 1 }, (_, i) => ({
        pageNum: i + 1,
        width: 612,
        height: 792,
    }));
}

function fieldsForPage(fileId: number, pageNum: number) {
    return fields.value.filter(
        (f) => f.document_file_id === fileId && f.page_number === pageNum,
    );
}

onMounted(() => {
    loadAllPdfs();
});

async function loadAllPdfs() {
    loadingPdf.value = true;
    const files = props.document.files ?? [];

    for (const file of files) {
        await loadPdfData(file);
    }

    loadingPdf.value = false;
    await nextTick();
    await renderAllPages();
}

async function loadPdfData(file: DocumentFile) {
    const url = `/esign/documents/${props.document.id}/pdf/${file.id}`;
    try {
        const pdf = await pdfjsLib.getDocument(url).promise;
        pdfDocs.value[file.id] = markRaw(pdf);

        const pages: { pageNum: number; width: number; height: number }[] = [];
        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const vp = page.getViewport({ scale: 1 });
            pages.push({ pageNum: i, width: vp.width, height: vp.height });
        }
        filePagesMap.value[file.id] = pages;
    } catch {
        filePagesMap.value[file.id] = generatePlaceholderPages(file);
    }
}

async function renderAllPages() {
    for (const fileId of Object.keys(pdfDocs.value)) {
        await renderFilePages(Number(fileId));
    }
}

async function renderFilePages(fileId: number) {
    const pdf = pdfDocs.value[fileId];
    const pages = filePagesMap.value[fileId];
    if (!pdf || !pages) return;

    for (const page of pages) {
        const key = canvasKey(fileId, page.pageNum);
        const canvas = canvasRefs.value[key];
        if (!canvas) continue;

        const pdfPage = await pdf.getPage(page.pageNum);
        const viewport = pdfPage.getViewport({ scale: zoom.value });
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const ctx = canvas.getContext('2d');
        if (!ctx) continue;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        await pdfPage.render({ canvasContext: ctx, viewport } as any).promise;
    }
}

function setCanvasRef(fileId: number, pageNum: number, el: any) {
    if (el) canvasRefs.value[canvasKey(fileId, pageNum)] = el;
}

function fieldDisplayValue(field: DocumentField): string {
    const val = field.value?.value;
    if (!val) return '';
    if (field.type === 'checkbox') return val === 'true' ? '\u2713' : '';
    if (field.type === 'date_signed') return val;
    return val;
}

function isImageField(field: DocumentField): boolean {
    const val = field.value?.value;
    return (
        ['signature', 'initials', 'stamp'].includes(field.type) &&
        !!val &&
        val.startsWith('data:image')
    );
}

async function downloadPdf() {
    downloading.value = true;

    try {
        const response = await axios.get(
            `/esign/documents/${props.document.id}/signed/${props.recipient.id}/flatten-data`,
        );
        const { files, document_title, recipient_name } = response.data;

        const allPdfBytes: Uint8Array[] = [];

        for (const file of files) {
            const pdfBytes = await flattenPdf(file.pdf_base64, file.fields);
            allPdfBytes.push(pdfBytes);
        }

        const filename = `${document_title || 'document'}-signed-by-${recipient_name || 'recipient'}.pdf`;

        // Merge multiple PDFs into one
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
        downloading.value = false;
    }
}
</script>

<template>
    <Head :title="`Signed Copy - ${recipient.name || recipient.email}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-4rem)] flex-col">
            <!-- Header -->
            <div
                class="flex shrink-0 items-center justify-between border-b bg-card px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    <a
                        :href="`/esign/documents/${document.id}`"
                        class="rounded-md p-1.5 hover:bg-accent"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </a>
                    <div>
                        <h1 class="text-sm font-medium">
                            {{ document.title }}
                        </h1>
                        <p class="text-xs text-muted-foreground">
                            Signed by {{ recipient.name || recipient.email }}
                            <span v-if="recipient.signed_at" class="ml-1">
                                on
                                {{
                                    new Date(
                                        recipient.signed_at,
                                    ).toLocaleDateString()
                                }}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="downloading"
                        @click="downloadPdf"
                    >
                        <Download class="mr-2 h-4 w-4" />
                        {{
                            downloading
                                ? 'Generating...'
                                : 'Download Signed PDF'
                        }}
                    </Button>
                </div>
            </div>

            <!-- Loading -->
            <div
                v-if="loadingPdf"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <div
                        class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"
                    />
                    <p class="text-sm text-muted-foreground">
                        Loading signed document...
                    </p>
                </div>
            </div>

            <!-- PDF + Signed Fields -->
            <div v-else class="flex-1 overflow-auto bg-muted/30 p-6">
                <div class="mx-auto flex max-w-4xl flex-col items-center gap-4">
                    <template v-for="file in document.files" :key="file.id">
                        <div
                            v-for="page in getPagesForFile(file)"
                            :key="canvasKey(file.id, page.pageNum)"
                            class="relative bg-white shadow-lg"
                            :style="{
                                width: `${page.width * zoom}px`,
                                height: `${page.height * zoom}px`,
                            }"
                        >
                            <canvas
                                :ref="
                                    (el) =>
                                        setCanvasRef(file.id, page.pageNum, el)
                                "
                                class="absolute top-0 left-0 h-full w-full"
                            />

                            <!-- Signed field values (read-only) -->
                            <div
                                v-for="field in fieldsForPage(
                                    file.id,
                                    page.pageNum,
                                )"
                                :key="field.id"
                                class="absolute overflow-hidden rounded border border-green-400/50"
                                :style="{
                                    left: `${field.position_x}%`,
                                    top: `${field.position_y}%`,
                                    width: `${field.width}%`,
                                    height: `${field.height}%`,
                                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                                }"
                            >
                                <!-- Signature / Initials / Stamp images -->
                                <img
                                    v-if="isImageField(field)"
                                    :src="field.value?.value"
                                    class="h-full w-full object-contain"
                                    alt=""
                                />

                                <!-- Checkbox -->
                                <span
                                    v-else-if="field.type === 'checkbox'"
                                    class="flex h-full w-full items-center justify-center text-sm font-bold text-black"
                                >
                                    {{ fieldDisplayValue(field) }}
                                </span>

                                <!-- Text / Date / Other values -->
                                <span
                                    v-else
                                    class="flex h-full w-full items-center px-1 text-xs text-black"
                                >
                                    {{ fieldDisplayValue(field) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
