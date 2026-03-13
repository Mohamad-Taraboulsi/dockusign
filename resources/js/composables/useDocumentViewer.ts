import { pdfjsLib } from '@/utils/pdfWorker';
import { ref, computed, onUnmounted  } from 'vue';
import type {Ref} from 'vue';

export type PageInfo = {
    pageNumber: number;
    width: number;
    height: number;
};

export function useDocumentViewer(url: Ref<string> | string) {
    const pages = ref<PageInfo[]>([]);
    const currentPage = ref(1);
    const zoom = ref(1.0);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let pdfDocument: pdfjsLib.PDFDocumentProxy | null = null;

    const totalPages = computed(() => pages.value.length);

    async function loadDocument(): Promise<void> {
        const pdfUrl = typeof url === 'string' ? url : url.value;
        if (!pdfUrl) return;

        loading.value = true;
        error.value = null;
        pages.value = [];

        try {
            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            pdfDocument = await loadingTask.promise;

            const pageInfos: PageInfo[] = [];
            for (let i = 1; i <= pdfDocument.numPages; i++) {
                const page = await pdfDocument.getPage(i);
                const viewport = page.getViewport({ scale: 1.0 });
                pageInfos.push({
                    pageNumber: i,
                    width: viewport.width,
                    height: viewport.height,
                });
            }
            pages.value = pageInfos;
            currentPage.value = 1;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load PDF document';
        } finally {
            loading.value = false;
        }
    }

    async function renderPage(canvas: HTMLCanvasElement, pageNumber: number): Promise<void> {
        if (!pdfDocument) {
            throw new Error('PDF document not loaded');
        }
        if (pageNumber < 1 || pageNumber > pdfDocument.numPages) {
            throw new Error(`Invalid page number: ${pageNumber}`);
        }

        const page = await pdfDocument.getPage(pageNumber);
        const viewport = page.getViewport({ scale: zoom.value });

        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const context = canvas.getContext('2d');
        if (!context) {
            throw new Error('Could not get canvas 2D context');
        }

        await page.render({
            canvasContext: context,
            viewport,
        } as any).promise;
    }

    function setZoom(newZoom: number): void {
        zoom.value = Math.min(3.0, Math.max(0.5, newZoom));
    }

    function scrollToPage(pageNumber: number): void {
        if (pageNumber < 1 || pageNumber > totalPages.value) return;
        currentPage.value = pageNumber;

        const pageElement = document.querySelector(`[data-page-number="${pageNumber}"]`);
        if (pageElement) {
            pageElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Load document immediately
    loadDocument();

    onUnmounted(() => {
        if (pdfDocument) {
            pdfDocument.destroy();
            pdfDocument = null;
        }
    });

    return {
        pages,
        currentPage,
        zoom,
        loading,
        error,
        totalPages,
        loadDocument,
        renderPage,
        setZoom,
        scrollToPage,
    };
}
