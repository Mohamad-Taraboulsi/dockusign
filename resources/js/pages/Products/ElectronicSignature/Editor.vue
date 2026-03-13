<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Send, ArrowLeft, ZoomIn, ZoomOut } from 'lucide-vue-next';
import * as pdfjsLib from 'pdfjs-dist';
import { ref, computed, onMounted, watch, nextTick, markRaw } from 'vue';
import FieldPalette from '@/components/esign/FieldPalette.vue';
import FieldProperties from '@/components/esign/FieldProperties.vue';
import RecipientTabs from '@/components/esign/RecipientTabs.vue';
import { Button } from '@/components/ui/button';
import type {
    Document,
    DocumentField,
    FieldType,
    DocumentFile,
} from '@/types/esign';
import { RECIPIENT_COLORS } from '@/types/esign';

// Configure worker — try multiple paths for compatibility
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
}>();

const fields = ref<DocumentField[]>(props.document.fields ?? []);
const selectedFieldId = ref<string | null>(null);
const selectedRecipientId = ref<string | null>(
    props.document.recipients?.[0]?.id ?? null,
);
const zoom = ref(1);

// Track pages per file: Map<fileId, { pageNum, width, height }[]>
const filePagesMap = ref<
    Record<number, { pageNum: number; width: number; height: number }[]>
>({});
const pdfDocs = ref<Record<number, any>>({});
const canvasRefs = ref<Record<string, HTMLCanvasElement>>({});
const viewerRef = ref<HTMLDivElement | null>(null);
const sending = ref(false);
const loadingPdf = ref(true);
const pdfError = ref<string | null>(null);

const recipients = computed(() => props.document.recipients ?? []);
const selectedField = computed(
    () => fields.value.find((f) => f.id === selectedFieldId.value) ?? null,
);

const recipientColorMap = computed(() => {
    const map: Record<string, string> = {};
    recipients.value.forEach((r, i) => {
        map[r.id] = RECIPIENT_COLORS[i % RECIPIENT_COLORS.length];
    });
    return map;
});

const selectedRecipientName = computed(() => {
    if (!selectedField.value) return '';
    const r = recipients.value.find(
        (r) => r.id === selectedField.value!.recipient_id,
    );
    return r?.name || r?.email || '';
});

const DEFAULT_PLACEHOLDERS: Record<string, string> = {
    text_name: 'Full Name',
    text_title: 'Title',
    text_email: 'Email',
    note: 'Note',
    signature: 'Signature',
    initials: 'Initials',
    stamp: 'Stamp',
    checkbox: 'Checkbox',
    dropdown: 'Dropdown',
    date_signed: 'Date Signed',
    attachment: 'Attachment',
    radio: 'Radio',
};

function fieldPlaceholder(field: DocumentField): string {
    return field.placeholder || field.label || DEFAULT_PLACEHOLDERS[field.type] || field.type.replace('_', ' ');
}

function fieldsForPage(fileId: number, pageNum: number) {
    return fields.value.filter(
        (f) => f.document_file_id === fileId && f.page_number === pageNum,
    );
}

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

// PDF Loading — load each file's data, then render after canvases are in DOM
async function loadAllPdfs() {
    loadingPdf.value = true;
    pdfError.value = null;

    const files = props.document.files ?? [];
    if (files.length === 0) {
        loadingPdf.value = false;
        return;
    }

    // Phase 1: Load PDF data and page dimensions (no rendering yet)
    for (const file of files) {
        await loadPdfData(file);
    }

    // Phase 2: Show canvases by toggling loadingPdf off
    loadingPdf.value = false;

    // Phase 3: Wait for canvas elements to mount in the DOM, then render
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
            const viewport = page.getViewport({ scale: 1 });
            pages.push({
                pageNum: i,
                width: viewport.width,
                height: viewport.height,
            });
        }
        filePagesMap.value[file.id] = pages;
    } catch (e) {
        console.error(`Failed to load PDF for file ${file.id}:`, e);
        pdfError.value = `Failed to load document: ${file.original_name}`;
        filePagesMap.value[file.id] = generatePlaceholderPages(file);
    }
}

async function renderFilePages(fileId: number) {
    const pdf = pdfDocs.value[fileId];
    const pages = filePagesMap.value[fileId];
    if (!pdf || !pages) return;

    for (const page of pages) {
        await renderPage(fileId, page.pageNum);
    }
}

async function renderPage(fileId: number, pageNum: number) {
    const key = canvasKey(fileId, pageNum);
    const canvas = canvasRefs.value[key];
    const pdf = pdfDocs.value[fileId];
    if (!canvas || !pdf) return;

    const page = await pdf.getPage(pageNum);
    const viewport = page.getViewport({ scale: zoom.value });

    // Set canvas buffer dimensions to match viewport
    canvas.width = viewport.width;
    canvas.height = viewport.height;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Clear before rendering
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    await page.render({
        canvasContext: ctx,
        viewport,
    } as any).promise;
}

async function renderAllPages() {
    for (const fileId of Object.keys(pdfDocs.value)) {
        await renderFilePages(Number(fileId));
    }
}

watch(zoom, async () => {
    await nextTick();
    renderAllPages();
});

onMounted(() => {
    loadAllPdfs();
});

// Field CRUD via axios
async function addField(
    type: FieldType,
    fileId: number,
    pageNum: number,
    x: number,
    y: number,
    w: number,
    h: number,
) {
    if (!selectedRecipientId.value) return;

    try {
        const response = await axios.post(
            `/esign/documents/${props.document.id}/fields`,
            {
                document_file_id: fileId,
                recipient_id: selectedRecipientId.value,
                type,
                page_number: pageNum,
                position_x: x,
                position_y: y,
                width: w,
                height: h,
                is_required: true,
            },
        );
        const field = response.data;
        fields.value.push(field);
        selectedFieldId.value = field.id;
    } catch (e) {
        console.error('Failed to add field:', e);
    }
}

async function updateField(fieldId: string, data: Partial<DocumentField>) {
    try {
        const response = await axios.put(
            `/esign/documents/${props.document.id}/fields/${fieldId}`,
            data,
        );
        const updated = response.data;
        const idx = fields.value.findIndex((f) => f.id === fieldId);
        if (idx >= 0) fields.value[idx] = updated;
    } catch (e) {
        console.error('Failed to update field:', e);
    }
}

async function deleteField(fieldId: string) {
    try {
        await axios.delete(
            `/esign/documents/${props.document.id}/fields/${fieldId}`,
        );
        fields.value = fields.value.filter((f) => f.id !== fieldId);
        if (selectedFieldId.value === fieldId) selectedFieldId.value = null;
    } catch (e) {
        console.error('Failed to delete field:', e);
    }
}

// Drag and drop from palette
function handlePageDrop(e: DragEvent, fileId: number, pageNum: number) {
    e.preventDefault();
    const type = (e.dataTransfer?.getData('application/esign-field-type') ||
        e.dataTransfer?.getData('field-type')) as FieldType;
    if (!type) return;

    const pageEl = e.currentTarget as HTMLElement;
    const rect = pageEl.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    const defaults: Record<string, { w: number; h: number }> = {
        signature: { w: 20, h: 5 },
        initials: { w: 10, h: 5 },
        stamp: { w: 15, h: 8 },
        text_name: { w: 20, h: 3 },
        text_title: { w: 20, h: 3 },
        text_email: { w: 20, h: 3 },
        checkbox: { w: 3, h: 3 },
        dropdown: { w: 20, h: 3 },
        radio: { w: 3, h: 3 },
        note: { w: 25, h: 5 },
        attachment: { w: 15, h: 5 },
        date_signed: { w: 15, h: 3 },
    };

    const size = defaults[type] ?? { w: 15, h: 3 };
    addField(
        type,
        fileId,
        pageNum,
        Math.max(0, x - size.w / 2),
        Math.max(0, y - size.h / 2),
        size.w,
        size.h,
    );
}

// Field dragging on the document
let dragState: {
    fieldId: string;
    startX: number;
    startY: number;
    origX: number;
    origY: number;
    rect: DOMRect;
} | null = null;

function startFieldDrag(e: MouseEvent, field: DocumentField) {
    e.preventDefault();
    e.stopPropagation();
    selectedFieldId.value = field.id;

    const pageEl = (e.currentTarget as HTMLElement).closest(
        '[data-page]',
    ) as HTMLElement;
    if (!pageEl) return;

    const rect = pageEl.getBoundingClientRect();
    dragState = {
        fieldId: field.id,
        startX: e.clientX,
        startY: e.clientY,
        origX: field.position_x,
        origY: field.position_y,
        rect,
    };

    const onMove = (ev: MouseEvent) => {
        if (!dragState) return;
        const dx =
            ((ev.clientX - dragState.startX) / dragState.rect.width) * 100;
        const dy =
            ((ev.clientY - dragState.startY) / dragState.rect.height) * 100;
        const idx = fields.value.findIndex((f) => f.id === dragState!.fieldId);
        if (idx >= 0) {
            fields.value[idx] = {
                ...fields.value[idx],
                position_x: Math.max(
                    0,
                    Math.min(
                        100 - fields.value[idx].width,
                        dragState!.origX + dx,
                    ),
                ),
                position_y: Math.max(
                    0,
                    Math.min(
                        100 - fields.value[idx].height,
                        dragState!.origY + dy,
                    ),
                ),
            };
        }
    };

    const onUp = () => {
        if (dragState) {
            const f = fields.value.find((f) => f.id === dragState!.fieldId);
            if (f)
                updateField(f.id, {
                    position_x: f.position_x,
                    position_y: f.position_y,
                });
        }
        dragState = null;
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
    };

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function sendDocument() {
    sending.value = true;
    router.post(
        `/esign/documents/${props.document.id}/send`,
        {},
        {
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function setCanvasRef(fileId: number, pageNum: number, el: any) {
    const key = canvasKey(fileId, pageNum);
    if (el) {
        canvasRefs.value[key] = el;
    }
}
</script>

<template>
    <Head :title="`Editor - ${document.title}`" />

    <div class="flex h-screen flex-col bg-background">
        <!-- Top Bar -->
        <header
            class="flex h-14 shrink-0 items-center justify-between border-b bg-card px-4"
        >
            <div class="flex items-center gap-3">
                <a
                    :href="`/esign/documents`"
                    class="rounded-md p-1.5 hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                </a>
                <h1 class="text-sm font-medium">{{ document.title }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1 rounded-md border p-1">
                    <button
                        class="rounded p-1 hover:bg-accent"
                        @click="zoom = Math.max(0.5, zoom - 0.25)"
                    >
                        <ZoomOut class="h-4 w-4" />
                    </button>
                    <span class="min-w-[3rem] text-center text-xs"
                        >{{ Math.round(zoom * 100) }}%</span
                    >
                    <button
                        class="rounded p-1 hover:bg-accent"
                        @click="zoom = Math.min(3, zoom + 0.25)"
                    >
                        <ZoomIn class="h-4 w-4" />
                    </button>
                </div>
                <Button @click="sendDocument" :disabled="sending" size="sm">
                    <Send class="mr-2 h-4 w-4" />
                    {{ sending ? 'Sending...' : 'Send' }}
                </Button>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <!-- Left: Field Palette -->
            <aside class="w-56 shrink-0 overflow-y-auto border-r bg-card p-3">
                <RecipientTabs
                    :recipients="recipients"
                    :selected-id="selectedRecipientId"
                    :colors="RECIPIENT_COLORS"
                    @select="selectedRecipientId = $event"
                    class="mb-4"
                />
                <FieldPalette />
            </aside>

            <!-- Center: PDF Viewer -->
            <main ref="viewerRef" class="flex-1 overflow-auto bg-muted/30 p-6">
                <!-- Loading state -->
                <div
                    v-if="loadingPdf"
                    class="flex h-full items-center justify-center"
                >
                    <div class="text-center">
                        <div
                            class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"
                        />
                        <p class="text-sm text-muted-foreground">
                            Loading document...
                        </p>
                    </div>
                </div>

                <!-- Error state -->
                <div
                    v-else-if="pdfError && Object.keys(pdfDocs).length === 0"
                    class="flex h-full items-center justify-center"
                >
                    <div class="text-center">
                        <p class="text-sm text-destructive">{{ pdfError }}</p>
                        <button
                            class="mt-2 text-sm text-primary underline"
                            @click="loadAllPdfs"
                        >
                            Retry
                        </button>
                    </div>
                </div>

                <!-- Document pages -->
                <div v-else class="mx-auto flex flex-col items-center gap-4">
                    <template v-for="file in document.files" :key="file.id">
                        <div
                            v-for="page in getPagesForFile(file)"
                            :key="canvasKey(file.id, page.pageNum)"
                            class="relative bg-white shadow-lg"
                            :style="{
                                width: `${page.width * zoom}px`,
                                height: `${page.height * zoom}px`,
                            }"
                            data-page
                            @dragover.prevent
                            @drop="
                                handlePageDrop($event, file.id, page.pageNum)
                            "
                            @click="selectedFieldId = null"
                        >
                            <!-- PDF canvas — explicit width/height to match container -->
                            <canvas
                                :ref="
                                    (el) =>
                                        setCanvasRef(file.id, page.pageNum, el)
                                "
                                class="absolute top-0 left-0 h-full w-full"
                            />

                            <!-- Field overlays -->
                            <div
                                v-for="field in fieldsForPage(
                                    file.id,
                                    page.pageNum,
                                )"
                                :key="field.id"
                                class="absolute cursor-move rounded border-2 transition-shadow select-none"
                                :class="
                                    selectedFieldId === field.id
                                        ? 'z-10 shadow-lg ring-2 ring-primary'
                                        : 'hover:shadow-md'
                                "
                                :style="{
                                    left: `${field.position_x}%`,
                                    top: `${field.position_y}%`,
                                    width: `${field.width}%`,
                                    height: `${field.height}%`,
                                    borderColor:
                                        recipientColorMap[field.recipient_id] ??
                                        '#3B82F6',
                                    backgroundColor: `${recipientColorMap[field.recipient_id] ?? '#3B82F6'}15`,
                                }"
                                @mousedown="startFieldDrag($event, field)"
                                @click.stop="selectedFieldId = field.id"
                            >
                                <span
                                    class="absolute -top-5 left-0 rounded-t px-1.5 py-0.5 text-[10px] font-medium whitespace-nowrap text-white"
                                    :style="{
                                        backgroundColor:
                                            recipientColorMap[
                                                field.recipient_id
                                            ] ?? '#3B82F6',
                                    }"
                                >
                                    {{ field.type.replace('_', ' ') }}
                                </span>
                                <span
                                    class="flex h-full w-full items-center justify-center px-1 text-xs text-black"
                                >
                                    {{ fieldPlaceholder(field) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </main>

            <!-- Right: Field Properties -->
            <aside class="w-64 shrink-0 overflow-y-auto border-l bg-card p-4">
                <FieldProperties
                    v-if="selectedField"
                    :field="selectedField"
                    :recipient-name="selectedRecipientName"
                    @update="(id, changes) => updateField(id, changes)"
                    @delete="(id) => deleteField(id)"
                />
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-12 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        Select a field to edit its properties, or drag a field
                        from the palette onto the document.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
