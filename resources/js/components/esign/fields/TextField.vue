<script setup lang="ts">
import { Input } from '@/components/ui/input';
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
        class="relative flex flex-col justify-center rounded-md border-2"
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

        <span
            v-if="readonly"
            class="truncate px-2 py-1 text-sm"
        >
            {{ value || '' }}
        </span>

        <Input
            v-else
            :model-value="value ?? ''"
            :placeholder="field.placeholder ?? ''"
            class="h-full w-full rounded-none border-0 shadow-none focus-visible:ring-0"
            @update:model-value="emit('update:value', String($event))"
        />
    </div>
</template>
