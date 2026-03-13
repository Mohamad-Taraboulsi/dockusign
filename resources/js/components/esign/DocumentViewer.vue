<script setup lang="ts">
import { Minus, Plus, Maximize } from 'lucide-vue-next';
import { pdfjsLib } from '@/utils/pdfWorker';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch,
    markRaw,
} from 'vue';
import { Button } from '@/components/ui/button';
import type { DocumentField } from '@/types/esign';
import DocumentPage from './DocumentPage.vue';


const props = withDefaults(
    defineProps<{
        fileUrl: string;
        fields: DocumentField[];
        zoom: number;
        readonly: boolean;
        recipientColors: Record<string, string>;
        selectedFieldId?: string | null;
    }>(),
    {
        zoom: 1,
        readonly: false,
        selectedFieldId: null,
    },
);

const emit = defineEmits<{
    'update:zoom': [zoom: number];
    selectField: [fieldId: string];
    updateFieldPosition: [
        fieldId: string,
        position: { position_x: number; position_y: number },
    ];
    updateFieldSize: [fieldId: string, size: { width: number; height: number }];
}>();

const containerRef = ref<HTMLDivElement | null>(null);
const pageRefs = ref<InstanceType<typeof DocumentPage>[]>([]);
const pageCount = ref(0);
const currentZoom = ref(props.zoom);
const pdfDoc = ref<pdfjsLib.PDFDocumentProxy | null>(null);
const loading = ref(true);

const zoomSteps = [0.5, 0.75, 1, 1.25, 1.5, 2];

watch(
    () => props.zoom,
    (val) => {
        currentZoom.value = val;
    },
);

const fieldsByPage = computed(() => {
    const map: Record<number, DocumentField[]> = {};
    for (const field of props.fields) {
        if (!map[field.page_number]) {
            map[field.page_number] = [];
        }
        map[field.page_number].push(field);
    }
    return map;
});

async function loadPdf() {
    loading.value = true;
    try {
        const doc = await pdfjsLib.getDocument(props.fileUrl).promise;
        pdfDoc.value = markRaw(doc);
        pageCount.value = doc.numPages;
        await nextTick();
        await renderAllPages();
    } finally {
        loading.value = false;
    }
}

async function renderAllPages() {
    if (!pdfDoc.value) return;

    for (let i = 0; i < pageCount.value; i++) {
        const pageComp = pageRefs.value[i];
        if (!pageComp?.canvasRef) continue;

        const page = await pdfDoc.value.getPage(i + 1);
        const viewport = page.getViewport({ scale: currentZoom.value * 1.5 });

        const canvas = pageComp.canvasRef;
        const context = canvas.getContext('2d');
        if (!context) continue;

        canvas.height = viewport.height;
        canvas.width = viewport.width;

        await page.render({
            canvasContext: context,
            viewport,
        } as any).promise;
    }
}

function zoomIn() {
    const idx = zoomSteps.findIndex((s) => s >= currentZoom.value);
    const next =
        idx < zoomSteps.length - 1
            ? zoomSteps[idx + 1]
            : zoomSteps[zoomSteps.length - 1];
    currentZoom.value = next;
    emit('update:zoom', next);
    nextTick(() => renderAllPages());
}

function zoomOut() {
    const idx = zoomSteps.findIndex((s) => s >= currentZoom.value);
    const prev = idx > 0 ? zoomSteps[idx - 1] : zoomSteps[0];
    currentZoom.value = prev;
    emit('update:zoom', prev);
    nextTick(() => renderAllPages());
}

function zoomFit() {
    currentZoom.value = 1;
    emit('update:zoom', 1);
    nextTick(() => renderAllPages());
}

const zoomPercentage = computed(() => Math.round(currentZoom.value * 100));

watch(() => props.fileUrl, loadPdf);

onMounted(() => {
    loadPdf();
});

onUnmounted(() => {
    pdfDoc.value?.destroy();
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Toolbar -->
        <div
            class="flex items-center justify-center gap-2 border-b bg-muted/50 px-4 py-2"
        >
            <Button
                variant="outline"
                size="icon-sm"
                @click="zoomOut"
                :disabled="currentZoom <= zoomSteps[0]"
            >
                <Minus class="size-4" />
            </Button>
            <span
                class="w-14 text-center text-sm font-medium text-muted-foreground"
            >
                {{ zoomPercentage }}%
            </span>
            <Button
                variant="outline"
                size="icon-sm"
                @click="zoomIn"
                :disabled="currentZoom >= zoomSteps[zoomSteps.length - 1]"
            >
                <Plus class="size-4" />
            </Button>
            <Button variant="outline" size="sm" @click="zoomFit">
                <Maximize class="size-4" />
                <span>Fit</span>
            </Button>
        </div>

        <!-- Scrollable pages container -->
        <div
            ref="containerRef"
            class="flex-1 overflow-auto bg-neutral-200 p-4 dark:bg-neutral-800"
        >
            <div v-if="loading" class="flex items-center justify-center py-20">
                <div class="text-sm text-muted-foreground">
                    Loading document...
                </div>
            </div>
            <div v-else class="flex flex-col items-center gap-4">
                <DocumentPage
                    v-for="page in pageCount"
                    :key="page"
                    :ref="
                        (el) => {
                            if (el)
                                pageRefs[page - 1] = el as InstanceType<
                                    typeof DocumentPage
                                >;
                        }
                    "
                    :page-number="page"
                    :fields="fieldsByPage[page] ?? []"
                    :zoom="currentZoom"
                    :readonly="readonly"
                    :recipient-colors="recipientColors"
                    :selected-field-id="selectedFieldId"
                    class="shadow-lg"
                    @select-field="(id) => emit('selectField', id)"
                    @update-field-position="
                        (id, pos) => emit('updateFieldPosition', id, pos)
                    "
                    @update-field-size="
                        (id, size) => emit('updateFieldSize', id, size)
                    "
                />
            </div>
        </div>
    </div>
</template>
