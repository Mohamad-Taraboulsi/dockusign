import interact from 'interactjs';
import { onMounted, onUnmounted } from 'vue';
import type { FieldType } from '@/types/esign';

export type DragAndDropCallbacks = {
    onDrop: (fieldType: FieldType, fileId: number, pageNumber: number, x: number, y: number) => void;
    onMove: (fieldId: string, x: number, y: number) => void;
    onResize: (fieldId: string, width: number, height: number) => void;
};

export type DragAndDropOptions = {
    paletteSelector: string;
    dropZoneSelector: string;
    fieldSelector: string;
    callbacks: DragAndDropCallbacks;
};

function toPercentageX(px: number, containerWidth: number): number {
    return Math.min(100, Math.max(0, (px / containerWidth) * 100));
}

function toPercentageY(px: number, containerHeight: number): number {
    return Math.min(100, Math.max(0, (px / containerHeight) * 100));
}

export function useDragAndDrop(options: DragAndDropOptions) {
    const { paletteSelector, dropZoneSelector, fieldSelector, callbacks } = options;

    let paletteInteractable: ReturnType<typeof interact> | null = null;
    let fieldInteractable: ReturnType<typeof interact> | null = null;
    let dropzoneInteractable: ReturnType<typeof interact> | null = null;

    function setup(): void {
        // Palette items: draggable (creates a clone that can be dropped)
        paletteInteractable = interact(paletteSelector).draggable({
            inertia: false,
            autoScroll: true,
            listeners: {
                start(event) {
                    const target = event.target as HTMLElement;
                    target.classList.add('dragging');
                    target.setAttribute('data-drag-x', '0');
                    target.setAttribute('data-drag-y', '0');
                },
                move(event) {
                    const target = event.target as HTMLElement;
                    const x = (parseFloat(target.getAttribute('data-drag-x') ?? '0')) + event.dx;
                    const y = (parseFloat(target.getAttribute('data-drag-y') ?? '0')) + event.dy;

                    target.style.transform = `translate(${x}px, ${y}px)`;
                    target.setAttribute('data-drag-x', String(x));
                    target.setAttribute('data-drag-y', String(y));
                },
                end(event) {
                    const target = event.target as HTMLElement;
                    target.classList.remove('dragging');
                    target.style.transform = '';
                    target.removeAttribute('data-drag-x');
                    target.removeAttribute('data-drag-y');
                },
            },
        });

        // Drop zones: pages where palette items can be dropped
        dropzoneInteractable = interact(dropZoneSelector).dropzone({
            accept: paletteSelector,
            overlap: 0.25,
            ondrop(event) {
                const paletteItem = event.relatedTarget as HTMLElement;
                const dropTarget = event.target as HTMLElement;

                const fieldType = paletteItem.getAttribute('data-field-type') as FieldType;
                const fileId = parseInt(dropTarget.getAttribute('data-file-id') ?? '0', 10);
                const pageNumber = parseInt(dropTarget.getAttribute('data-page-number') ?? '1', 10);

                const dropRect = dropTarget.getBoundingClientRect();
                const x = toPercentageX(event.dragEvent.clientX - dropRect.left, dropRect.width);
                const y = toPercentageY(event.dragEvent.clientY - dropRect.top, dropRect.height);

                callbacks.onDrop(fieldType, fileId, pageNumber, x, y);
            },
        });

        // Placed fields: draggable + resizable
        fieldInteractable = interact(fieldSelector)
            .draggable({
                inertia: false,
                autoScroll: true,
                modifiers: [
                    interact.modifiers.restrictRect({
                        restriction: 'parent',
                        endOnly: false,
                    }),
                ],
                listeners: {
                    move(event) {
                        const target = event.target as HTMLElement;
                        const parent = target.parentElement;
                        if (!parent) return;

                        const parentRect = parent.getBoundingClientRect();

                        // Current position in pixels
                        const currentLeft = target.offsetLeft + event.dx;
                        const currentTop = target.offsetTop + event.dy;

                        const xPercent = toPercentageX(currentLeft, parentRect.width);
                        const yPercent = toPercentageY(currentTop, parentRect.height);

                        target.style.left = `${xPercent}%`;
                        target.style.top = `${yPercent}%`;

                        const fieldId = target.getAttribute('data-field-id');
                        if (fieldId) {
                            callbacks.onMove(fieldId, xPercent, yPercent);
                        }
                    },
                },
            })
            .resizable({
                edges: { left: false, right: true, bottom: true, top: false },
                modifiers: [
                    interact.modifiers.restrictSize({
                        min: { width: 20, height: 16 },
                    }),
                    interact.modifiers.restrictEdges({
                        outer: 'parent',
                    }),
                ],
                listeners: {
                    move(event) {
                        const target = event.target as HTMLElement;
                        const parent = target.parentElement;
                        if (!parent) return;

                        const parentRect = parent.getBoundingClientRect();

                        const widthPercent = toPercentageX(event.rect.width, parentRect.width);
                        const heightPercent = toPercentageY(event.rect.height, parentRect.height);

                        target.style.width = `${widthPercent}%`;
                        target.style.height = `${heightPercent}%`;

                        const fieldId = target.getAttribute('data-field-id');
                        if (fieldId) {
                            callbacks.onResize(fieldId, widthPercent, heightPercent);
                        }
                    },
                },
            });
    }

    function teardown(): void {
        if (paletteInteractable) {
            (paletteInteractable as unknown as { unset: () => void }).unset();
            paletteInteractable = null;
        }
        if (fieldInteractable) {
            (fieldInteractable as unknown as { unset: () => void }).unset();
            fieldInteractable = null;
        }
        if (dropzoneInteractable) {
            (dropzoneInteractable as unknown as { unset: () => void }).unset();
            dropzoneInteractable = null;
        }
    }

    onMounted(() => {
        setup();
    });

    onUnmounted(() => {
        teardown();
    });

    return {
        setup,
        teardown,
    };
}
