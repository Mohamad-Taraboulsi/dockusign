<script setup lang="ts">
import { ref } from 'vue';
import type { DocumentField } from '@/types/esign';

const props = defineProps<{
    field: DocumentField;
    value: string | null;
    readonly: boolean;
    color: string;
}>();

const emit = defineEmits<{
    'file-selected': [file: File];
}>();

const fileInput = ref<HTMLInputElement | null>(null);

function openFilePicker() {
    fileInput.value?.click();
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        emit('file-selected', file);
        target.value = '';
    }
}
</script>

<template>
    <div
        class="relative flex flex-col items-center justify-center rounded-md border-2 p-1"
        :style="{ borderColor: color }"
    >
        <span
            v-if="field.label"
            class="absolute top-0 left-1 text-[10px] leading-tight font-medium opacity-70"
            :style="{ color }"
        >
            {{ field.label }}
            <span v-if="field.is_required" class="text-red-500">*</span>
        </span>

        <div v-if="value" class="flex items-center gap-1 text-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48" />
            </svg>
            <span class="truncate">File attached</span>
        </div>

        <button
            v-else-if="!readonly"
            type="button"
            class="flex h-full w-full cursor-pointer items-center justify-center gap-1 text-xs font-medium opacity-80 transition-opacity hover:opacity-100"
            :style="{ color }"
            @click="openFilePicker"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="17 8 12 3 7 8" /><line x1="12" x2="12" y1="3" y2="15" />
            </svg>
            Upload
        </button>

        <span v-else class="text-muted-foreground text-xs italic">No attachment</span>

        <input
            ref="fileInput"
            type="file"
            class="hidden"
            @change="onFileChange"
        />
    </div>
</template>
