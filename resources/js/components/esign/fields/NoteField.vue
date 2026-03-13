<script setup lang="ts">
import type { DocumentField } from '@/types/esign';

const props = defineProps<{
    field: DocumentField;
    value: string | null;
    readonly: boolean;
    color: string;
}>();

const emit = defineEmits<{
    'update:value': [value: string];
}>();
</script>

<template>
    <div
        class="relative flex flex-col rounded-md border-2"
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

        <p
            v-if="readonly"
            class="whitespace-pre-wrap px-2 py-1 text-sm"
        >
            {{ value || '' }}
        </p>

        <textarea
            v-else
            :value="value ?? ''"
            :placeholder="field.placeholder ?? 'Enter note...'"
            class="placeholder:text-muted-foreground h-full w-full resize-none rounded-md bg-transparent px-2 py-1 text-sm outline-none"
            @input="emit('update:value', ($event.target as HTMLTextAreaElement).value)"
        />
    </div>
</template>
