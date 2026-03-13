<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import type { DocumentField } from '@/types/esign';

const props = defineProps<{
    field: DocumentField;
    value: boolean | null;
    readonly: boolean;
    color: string;
}>();

const emit = defineEmits<{
    'update:value': [value: boolean];
}>();

function onCheckedChange(checked: boolean | 'indeterminate') {
    if (checked === 'indeterminate') return;
    emit('update:value', checked);
}
</script>

<template>
    <div
        class="relative flex items-center gap-1.5 rounded-md border-2 px-1.5 py-1"
        :style="{ borderColor: color }"
    >
        <Checkbox
            :checked="!!value"
            :disabled="readonly"
            @update:checked="onCheckedChange"
        />
        <span
            v-if="field.label"
            class="text-[11px] leading-tight font-medium select-none"
            :style="{ color }"
        >
            {{ field.label }}
            <span v-if="field.is_required" class="text-red-500">*</span>
        </span>
    </div>
</template>
