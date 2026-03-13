<script setup lang="ts">
import { computed } from 'vue';
import type { DocumentField } from '@/types/esign';
import { FIELD_TYPES } from '@/types/esign';

const props = withDefaults(
    defineProps<{
        field: DocumentField;
        color: string;
        selected: boolean;
        readonly: boolean;
        zoom: number;
    }>(),
    {
        selected: false,
        readonly: false,
        zoom: 1,
    },
);

const emit = defineEmits<{
    select: [];
    updatePosition: [position: { position_x: number; position_y: number }];
    updateSize: [size: { width: number; height: number }];
}>();

const fieldTypeConfig = computed(() => {
    return FIELD_TYPES.find((ft) => ft.type === props.field.type);
});

const typeLabel = computed(() => {
    return fieldTypeConfig.value?.label ?? props.field.type;
});

const overlayStyle = computed(() => ({
    left: `${props.field.position_x}%`,
    top: `${props.field.position_y}%`,
    width: `${props.field.width}%`,
    height: `${props.field.height}%`,
    borderColor: props.color,
}));

function onMouseDown(e: MouseEvent) {
    if (props.readonly) return;
    emit('select');

    const overlay = (e.currentTarget as HTMLElement);
    const container = overlay.parentElement;
    if (!container) return;

    const containerRect = container.getBoundingClientRect();
    const startX = e.clientX;
    const startY = e.clientY;
    const startPosX = props.field.position_x;
    const startPosY = props.field.position_y;

    function onMouseMove(ev: MouseEvent) {
        const dx = ev.clientX - startX;
        const dy = ev.clientY - startY;
        const dxPct = (dx / containerRect.width) * 100;
        const dyPct = (dy / containerRect.height) * 100;

        const newX = Math.max(0, Math.min(100 - props.field.width, startPosX + dxPct));
        const newY = Math.max(0, Math.min(100 - props.field.height, startPosY + dyPct));

        emit('updatePosition', {
            position_x: Math.round(newX * 100) / 100,
            position_y: Math.round(newY * 100) / 100,
        });
    }

    function onMouseUp() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function onResizeMouseDown(e: MouseEvent, corner: string) {
    if (props.readonly) return;
    e.stopPropagation();

    const overlay = (e.currentTarget as HTMLElement).parentElement;
    const container = overlay?.parentElement;
    if (!container) return;

    const containerRect = container.getBoundingClientRect();
    const startX = e.clientX;
    const startY = e.clientY;
    const startWidth = props.field.width;
    const startHeight = props.field.height;
    const startPosX = props.field.position_x;
    const startPosY = props.field.position_y;

    function onMouseMove(ev: MouseEvent) {
        const dx = ((ev.clientX - startX) / containerRect.width) * 100;
        const dy = ((ev.clientY - startY) / containerRect.height) * 100;

        let newWidth = startWidth;
        let newHeight = startHeight;
        let newPosX = startPosX;
        let newPosY = startPosY;

        if (corner.includes('right')) {
            newWidth = Math.max(2, startWidth + dx);
        }
        if (corner.includes('left')) {
            newWidth = Math.max(2, startWidth - dx);
            newPosX = startPosX + (startWidth - newWidth);
        }
        if (corner.includes('bottom')) {
            newHeight = Math.max(1, startHeight + dy);
        }
        if (corner.includes('top')) {
            newHeight = Math.max(1, startHeight - dy);
            newPosY = startPosY + (startHeight - newHeight);
        }

        emit('updateSize', {
            width: Math.round(newWidth * 100) / 100,
            height: Math.round(newHeight * 100) / 100,
        });
        emit('updatePosition', {
            position_x: Math.round(Math.max(0, newPosX) * 100) / 100,
            position_y: Math.round(Math.max(0, newPosY) * 100) / 100,
        });
    }

    function onMouseUp() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

const resizeHandles = ['top-left', 'top-right', 'bottom-left', 'bottom-right'] as const;

const handlePositionClasses: Record<string, string> = {
    'top-left': '-top-1 -left-1 cursor-nwse-resize',
    'top-right': '-top-1 -right-1 cursor-nesw-resize',
    'bottom-left': '-bottom-1 -left-1 cursor-nesw-resize',
    'bottom-right': '-bottom-1 -right-1 cursor-nwse-resize',
};
</script>

<template>
    <div
        class="absolute z-10 border-2 transition-shadow"
        :class="[
            selected ? 'shadow-md ring-2 ring-offset-1' : 'hover:shadow-sm',
            readonly ? 'cursor-default' : 'cursor-move',
        ]"
        :style="overlayStyle"
        @mousedown="onMouseDown"
    >
        <!-- Type label badge -->
        <span
            class="absolute -top-5 left-0 truncate rounded-t px-1 text-[10px] leading-4 font-medium whitespace-nowrap text-white"
            :style="{ backgroundColor: color }"
        >
            {{ typeLabel }}
        </span>

        <!-- Field content area -->
        <div
            class="flex size-full items-center justify-center overflow-hidden text-xs opacity-50"
            :style="{ color }"
        >
            <span v-if="field.label" class="truncate px-1">{{ field.label }}</span>
            <span v-else-if="field.placeholder" class="truncate px-1 italic">{{ field.placeholder }}</span>
        </div>

        <!-- Resize handles (only when selected and not readonly) -->
        <template v-if="selected && !readonly">
            <div
                v-for="handle in resizeHandles"
                :key="handle"
                class="absolute size-2.5 rounded-full border border-white"
                :class="handlePositionClasses[handle]"
                :style="{ backgroundColor: color }"
                @mousedown="onResizeMouseDown($event, handle)"
            />
        </template>
    </div>
</template>
