<script setup lang="ts">
import type { DocumentField } from '@/types/esign';

const props = defineProps<{
    field: DocumentField;
    value: string | null;
    readonly: boolean;
    color: string;
}>();
</script>

<template>
    <div
        class="relative flex items-center rounded-md border-2 px-2 py-1"
        :style="{ borderColor: color }"
    >
        <span
            v-if="field.label"
            class="absolute -top-5 left-0 text-[10px] leading-tight font-medium"
            :style="{ color }"
        >
            {{ field.label }}
            <span v-if="field.is_required" class="text-red-500">*</span>
        </span>

        <span v-if="value" class="truncate text-sm">
            {{ value }}
        </span>

        <span v-else class="text-muted-foreground flex items-center gap-1 text-xs italic">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 2v4" /><path d="M16 2v4" /><rect width="18" height="18" x="3" y="4" rx="2" /><path d="M3 10h18" />
            </svg>
            Auto-filled on signing
        </span>
    </div>
</template>
