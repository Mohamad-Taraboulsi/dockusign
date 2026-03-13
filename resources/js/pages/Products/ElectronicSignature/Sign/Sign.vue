<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import * as pdfjsLib from 'pdfjs-dist';
import { ref, computed, onMounted, nextTick, markRaw } from 'vue';
import axios from 'axios';
import AiAnalysisPanel from '@/components/esign/signing/AiAnalysisPanel.vue';
import SignaturePadModal from '@/components/esign/signing/SignaturePadModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SigningLayout from '@/layouts/SigningLayout.vue';
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

const fieldValues = ref<Record<string, string>>({});
const fieldFiles = ref<Record<string, File>>({});
const signaturePadOpen = ref(false);
const signaturePadFieldId = ref<string | null>(null);
const signaturePadTitle = ref('Draw your signature');
const submitting = ref(false);
const declining = ref(false);
const declineReason = ref('');
const showDeclineForm = ref(false);
const loadingPdf = ref(true);
const documentText = ref<string | null>(null);

// Per-file page tracking
const filePagesMap = ref<
    Record<number, { pageNum: number; width: number; height: number }[]>
>({});
const pdfDocs = ref<Record<number, any>>({});
const canvasRefs = ref<Record<string, HTMLCanvasElement>>({});
const zoom = ref(1);

const fields = computed(() => props.document.fields ?? []);
const requiredFields = computed(() =>
    fields.value.filter((f) => f.is_required),
);
const filledCount = computed(() => {
    return requiredFields.value.filter((f) => {
        const val = fieldValues.value[f.id];
        return val !== undefined && val !== null && val !== '';
    }).length;
});
const progress = computed(() => {
    if (requiredFields.value.length === 0) return 100;
    return Math.round((filledCount.value / requiredFields.value.length) * 100);
});
const canSubmit = computed(
    () => filledCount.value >= requiredFields.value.length,
);

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

// Pre-fill auto fields
onMounted(() => {
    fields.value.forEach((field) => {
        if (field.value?.value) {
            fieldValues.value[field.id] = field.value.value;
        } else if (field.type === 'date_signed') {
            fieldValues.value[field.id] = new Date().toLocaleDateString();
        } else if (field.type === 'text_email') {
            fieldValues.value[field.id] = props.recipient.email;
        } else if (field.type === 'text_name') {
            fieldValues.value[field.id] = props.recipient.name ?? '';
        }
    });
    loadAllPdfs();
});

async function loadAllPdfs() {
    loadingPdf.value = true;
    const files = props.document.files ?? [];

    // Phase 1: Load PDF data and page dimensions (no rendering yet)
    for (const file of files) {
        await loadPdfData(file);
    }

    // Phase 2: Show canvases by toggling loadingPdf off
    loadingPdf.value = false;

    // Phase 3: Wait for canvas elements to mount in the DOM, then render
    await nextTick();
    await renderAllPages();

    // Phase 4: Extract text for AI features
    await extractDocumentText();
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

function fieldsForPage(fileId: number, pageNum: number) {
    return fields.value.filter(
        (f) => f.document_file_id === fileId && f.page_number === pageNum,
    );
}

function openSignaturePad(field: DocumentField) {
    signaturePadFieldId.value = field.id;
    signaturePadTitle.value =
        field.type === 'initials'
            ? 'Draw your initials'
            : 'Draw your signature';
    signaturePadOpen.value = true;
}

function onSignatureSave(dataUrl: string) {
    if (signaturePadFieldId.value) {
        fieldValues.value[signaturePadFieldId.value] = dataUrl;
    }
    signaturePadOpen.value = false;
}

async function submit() {
    submitting.value = true;

    try {
        // Step 1: Flatten and upload PDFs before submitting
        const files = props.document.files ?? [];
        for (const file of files) {
            try {
                // Fetch the original PDF using signing route
                const pdfResponse = await axios.get(
                    `/sign/${props.recipient.id}/pdf/${file.id}`,
                    { responseType: 'arraybuffer' },
                );
                const pdfBase64 = btoa(
                    new Uint8Array(pdfResponse.data).reduce(
                        (data, byte) => data + String.fromCharCode(byte),
                        '',
                    ),
                );

                // Get fields for this file
                const fileFields = fields.value
                    .filter((f) => f.document_file_id === file.id)
                    .map((f) => ({
                        id: f.id,
                        type: f.type,
                        page_number: f.page_number,
                        position_x: f.position_x,
                        position_y: f.position_y,
                        width: f.width,
                        height: f.height,
                        value: fieldValues.value[f.id] || null,
                    }));

                // Flatten the PDF with field values
                const flattenedBytes = await flattenPdf(pdfBase64, fileFields);
                const flattenedBase64 = btoa(
                    new Uint8Array(flattenedBytes).reduce(
                        (data, byte) => data + String.fromCharCode(byte),
                        '',
                    ),
                );

                // Upload flattened PDF
                await axios.post(
                    `/sign/${props.recipient.id}/upload-flattened`,
                    {
                        file_id: file.id,
                        pdf: flattenedBase64,
                    },
                );
            } catch (err) {
                console.error('Failed to flatten/upload PDF:', err);
            }
        }

        // Step 2: Submit form data
        const formData = new FormData();
        for (const [key, value] of Object.entries(fieldValues.value)) {
            formData.append(`fields[${key}]`, value ?? '');
        }
        for (const [key, file] of Object.entries(fieldFiles.value)) {
            formData.append(`files[${key}]`, file);
        }
        router.post(`/sign/${props.recipient.id}/submit`, formData, {
            forceFormData: true,
        });
    } finally {
        // Don't reset submitting here - let Inertia handle the redirect
    }
}

function decline() {
    declining.value = true;
    router.post(
        `/sign/${props.recipient.id}/decline`,
        { reason: declineReason.value },
        {
            onFinish: () => {
                declining.value = false;
            },
        },
    );
}

function setCanvasRef(fileId: number, pageNum: number, el: any) {
    if (el) canvasRefs.value[canvasKey(fileId, pageNum)] = el;
}

async function extractDocumentText() {
    const textParts: string[] = [];
    const files = props.document.files ?? [];

    for (const file of files) {
        const pdf = pdfDocs.value[file.id];
        if (!pdf) continue;

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const content = await page.getTextContent();
            const pageText = content.items
                .filter((item: any) => 'str' in item)
                .map((item: any) => item.str)
                .join(' ');
            if (pageText.trim()) {
                textParts.push(pageText);
            }
        }
    }

    documentText.value = textParts.join('\n\n');
}

const DEFAULT_PLACEHOLDERS: Record<string, string> = {
    text_name: 'Enter your full name',
    text_title: 'Enter your title',
    text_email: 'Enter your email address',
    note: 'Enter a note',
    signature: 'Click to sign',
    initials: 'Click to initial',
    stamp: 'Click to stamp',
    checkbox: 'Check',
    dropdown: 'Select an option...',
    date_signed: 'Date',
    attachment: 'Click to attach a file',
};

function fieldPlaceholder(field: DocumentField): string {
    return (
        field.placeholder ||
        field.label ||
        DEFAULT_PLACEHOLDERS[field.type] ||
        field.type.replace('_', ' ')
    );
}

function fieldBorderColor(field: DocumentField) {
    const val = fieldValues.value[field.id];
    if (val !== undefined && val !== null && val !== '') return '#10B981';
    if (field.is_required) return '#F59E0B';
    return '#94A3B8';
}
</script>

<template>
    <Head :title="`Sign - ${document.title}`" />

    <SigningLayout :document-title="document.title">
        <!-- Progress bar -->
        <div
            class="sticky top-0 z-20 border-b bg-card/95 px-4 py-3 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-4xl items-center justify-between gap-4"
            >
                <div class="flex-1">
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span
                            >{{ filledCount }} of
                            {{ requiredFields.length }} required fields</span
                        >
                        <span>{{ progress }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary transition-all"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showDeclineForm = !showDeclineForm"
                    >
                        Decline
                    </Button>
                    <Button
                        size="sm"
                        :disabled="!canSubmit || submitting"
                        @click="submit"
                    >
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        {{ submitting ? 'Submitting...' : 'Submit' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Decline form -->
        <div v-if="showDeclineForm" class="border-b bg-destructive/5 px-4 py-4">
            <div class="mx-auto flex max-w-4xl items-end gap-3">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium"
                        >Reason for declining (optional)</label
                    >
                    <Input
                        v-model="declineReason"
                        placeholder="Enter reason..."
                    />
                </div>
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="declining"
                    @click="decline"
                >
                    {{ declining ? 'Declining...' : 'Confirm Decline' }}
                </Button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loadingPdf" class="flex items-center justify-center py-20">
            <div class="text-center">
                <div
                    class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"
                />
                <p class="text-sm text-muted-foreground">Loading document...</p>
            </div>
        </div>

        <!-- PDF + Fields -->
        <div
            v-else
            class="mx-auto flex max-w-4xl flex-col items-center gap-4 p-6"
        >
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
                    <!-- PDF canvas -->
                    <canvas
                        :ref="(el) => setCanvasRef(file.id, page.pageNum, el)"
                        class="absolute top-0 left-0 h-full w-full"
                    />

                    <!-- Fields for signing -->
                    <div
                        v-for="field in fieldsForPage(file.id, page.pageNum)"
                        :key="field.id"
                        class="absolute overflow-hidden rounded border-2 transition-colors"
                        :style="{
                            left: `${field.position_x}%`,
                            top: `${field.position_y}%`,
                            width: `${field.width}%`,
                            height: `${field.height}%`,
                            borderColor: fieldBorderColor(field),
                            backgroundColor: `${fieldBorderColor(field)}10`,
                        }"
                    >
                        <!-- Signature / Initials / Stamp -->
                        <template
                            v-if="
                                ['signature', 'initials', 'stamp'].includes(
                                    field.type,
                                )
                            "
                        >
                            <img
                                v-if="fieldValues[field.id]"
                                :src="fieldValues[field.id]"
                                class="h-full w-full object-contain"
                                alt=""
                            />
                            <button
                                v-else
                                class="flex h-full w-full items-center justify-center text-xs font-medium text-black hover:bg-accent/50"
                                @click="openSignaturePad(field)"
                            >
                                {{ fieldPlaceholder(field) }}
                            </button>
                        </template>

                        <!-- Text fields -->
                        <template
                            v-else-if="
                                [
                                    'text_name',
                                    'text_title',
                                    'text_email',
                                    'note',
                                ].includes(field.type)
                            "
                        >
                            <input
                                v-model="fieldValues[field.id]"
                                class="h-full w-full bg-transparent px-1 text-xs text-black outline-none placeholder:text-gray-400"
                                :placeholder="fieldPlaceholder(field)"
                            />
                        </template>

                        <!-- Checkbox -->
                        <template v-else-if="field.type === 'checkbox'">
                            <label
                                class="flex h-full w-full cursor-pointer items-center justify-center"
                            >
                                <input
                                    type="checkbox"
                                    :checked="fieldValues[field.id] === 'true'"
                                    class="h-4 w-4"
                                    @change="
                                        fieldValues[field.id] = (
                                            $event.target as HTMLInputElement
                                        ).checked
                                            ? 'true'
                                            : ''
                                    "
                                />
                            </label>
                        </template>

                        <!-- Dropdown -->
                        <template v-else-if="field.type === 'dropdown'">
                            <select
                                v-model="fieldValues[field.id]"
                                class="h-full w-full bg-transparent px-1 text-xs text-black outline-none"
                            >
                                <option value="" class="text-gray-400">
                                    {{ fieldPlaceholder(field) }}
                                </option>
                                <option
                                    v-for="opt in (field.options as any)
                                        ?.items ?? []"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{ opt }}
                                </option>
                            </select>
                        </template>

                        <!-- Date signed -->
                        <template v-else-if="field.type === 'date_signed'">
                            <span
                                class="flex h-full w-full items-center px-1 text-xs text-black"
                            >
                                {{
                                    fieldValues[field.id] ||
                                    new Date().toLocaleDateString()
                                }}
                            </span>
                        </template>

                        <!-- Attachment -->
                        <template v-else-if="field.type === 'attachment'">
                            <label
                                class="flex h-full w-full cursor-pointer items-center justify-center text-xs text-black hover:bg-accent/50"
                            >
                                {{
                                    fieldFiles[field.id]
                                        ? fieldFiles[field.id].name
                                        : fieldPlaceholder(field)
                                }}
                                <input
                                    type="file"
                                    class="hidden"
                                    @change="
                                        (e) => {
                                            const f = (
                                                e.target as HTMLInputElement
                                            ).files?.[0];
                                            if (f) {
                                                fieldFiles[field.id] = f;
                                                fieldValues[field.id] = f.name;
                                            }
                                        }
                                    "
                                />
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <SignaturePadModal
            :open="signaturePadOpen"
            :title="signaturePadTitle"
            @save="onSignatureSave"
            @close="signaturePadOpen = false"
        />

        <AiAnalysisPanel
            :recipient-id="recipient.id"
            :document-text="documentText"
        />
    </SigningLayout>
</template>
