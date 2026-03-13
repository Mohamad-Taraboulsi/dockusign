<script setup lang="ts">
import { ref } from 'vue';
import type { DocumentField } from '@/types/esign';
import FieldOverlay from './FieldOverlay.vue';

const props = withDefaults(
    defineProps<{
        pageNumber: number;
        fileId?: number;
        fields: DocumentField[];
        zoom: number;
        readonly: boolean;
        recipientColors: Record<string, string>;
        selectedFieldId?: string | null;
    }>(),
    {
        fileId: 0,
        selectedFieldId: null,
    },
);

const emit = defineEmits<{
    selectField: [fieldId: string];
    updateFieldPosition: [fieldId: string, position: { position_x: number; position_y: number }];
    updateFieldSize: [fieldId: string, size: { width: number; height: number }];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);

defineExpose({
    canvasRef,
});
</script>

<template>
    <div
        class="relative inline-block"
        :data-page-number="pageNumber"
        :data-file-id="fileId"
    >
        <canvas
            ref="canvasRef"
            class="block"
        />

        <FieldOverlay
            v-for="field in fields"
            :key="field.id"
            :field="field"
            :color="recipientColors[field.recipient_id] ?? '#6B7280'"
            :selected="selectedFieldId === field.id"
            :readonly="readonly"
            :zoom="zoom"
            @select="emit('selectField', field.id)"
            @update-position="(pos) => emit('updateFieldPosition', field.id, pos)"
            @update-size="(size) => emit('updateFieldSize', field.id, size)"
        />
    </div>
</template>
