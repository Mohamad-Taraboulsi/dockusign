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
            alt="Signature"
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
                <path d="M12 20h9" /><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z" />
            </svg>
            Sign here
        </button>

        <span v-else class="text-muted-foreground text-xs italic">No signature</span>
    </div>
</template>
