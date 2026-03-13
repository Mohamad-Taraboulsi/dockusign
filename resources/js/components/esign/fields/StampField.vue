<script setup lang="ts">
import type { DocumentField } from '@/types/esign';

const props = defineProps<{
    field: DocumentField;
    value: string | null;
    readonly: boolean;
    color: string;
}>();

const emit = defineEmits<{
    click: [];
}>();
</script>

<template>
    <div
        class="relative flex flex-col items-center justify-center overflow-hidden rounded-md border-2 p-1"
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

        <img
            v-if="value"
            :src="value"
            alt="Stamp"
            class="max-h-full max-w-full object-contain"
        />

        <button
            v-else-if="!readonly"
            type="button"
            class="flex h-full w-full cursor-pointer items-center justify-center gap-1 text-xs font-medium opacity-80 transition-opacity hover:opacity-100"
            :style="{ color }"
            @click="emit('click')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 22h14" /><path d="M19.27 13.73A2.5 2.5 0 0 0 17.5 13h-11A2.5 2.5 0 0 0 4 15.5V17a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1.5c0-.66-.26-1.3-.73-1.77Z" /><path d="M14 13V8.5C14 7 15 7 15 5a3 3 0 0 0-3-3c-1.66 0-3 1.34-3 3 0 2 1 2 1 3.5V13" />
            </svg>
            Add stamp
        </button>

        <span v-else class="text-muted-foreground text-xs italic">No stamp</span>
    </div>
</template>
