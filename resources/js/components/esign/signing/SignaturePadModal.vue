<script setup lang="ts">
import { Undo2, Eraser } from 'lucide-vue-next';
import SignaturePad from 'signature_pad';
import { nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
    }>(),
    {
        title: 'Draw your signature',
    },
);

const emit = defineEmits<{
    save: [dataUrl: string];
    close: [];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
let signaturePad: SignaturePad | null = null;

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            await nextTick();
            await nextTick();
            initSignaturePad();
        } else {
            signaturePad?.off();
            signaturePad = null;
        }
    },
);

function initSignaturePad() {
    if (!canvasRef.value) return;

    const canvas = canvasRef.value;
    const container = canvas.parentElement;
    if (container) {
        canvas.width = container.clientWidth;
        canvas.height = container.clientHeight;
    }

    signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)',
    });
}

function clear() {
    signaturePad?.clear();
}

function undo() {
    if (!signaturePad) return;
    const data = signaturePad.toData();
    if (data.length > 0) {
        data.pop();
        signaturePad.fromData(data);
    }
}

function save() {
    if (!signaturePad || signaturePad.isEmpty()) return;
    const dataUrl = signaturePad.toDataURL('image/png');
    emit('save', dataUrl);
}

function onOpenChange(open: boolean) {
    if (!open) {
        emit('close');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    Draw your signature in the area below using your mouse or touch input.
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-hidden rounded-md border bg-white">
                <canvas
                    ref="canvasRef"
                    class="h-48 w-full cursor-crosshair"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="undo">
                    <Undo2 class="size-4" />
                    Undo
                </Button>
                <Button variant="outline" size="sm" @click="clear">
                    <Eraser class="size-4" />
                    Clear
                </Button>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('close')">
                    Cancel
                </Button>
                <Button @click="save">
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
