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
            alt="Initials"
            class="max-h-full max-w-full object-contain"
        />

        <button
            v-else-if="!readonly"
            type="button"
            class="flex h-full w-full cursor-pointer items-center justify-center gap-1 text-[11px] font-medium opacity-80 transition-opacity hover:opacity-100"
            :style="{ color }"
            @click="emit('click')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4 7 4 4 20 4 20 7" /><line x1="9" x2="15" y1="20" y2="20" /><line x1="12" x2="12" y1="4" y2="20" />
            </svg>
            Initials
        </button>

        <span v-else class="text-muted-foreground text-[11px] italic">No initials</span>
    </div>
</template>
