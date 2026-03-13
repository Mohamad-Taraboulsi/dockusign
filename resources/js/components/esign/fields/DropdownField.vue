<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const items = computed<string[]>(() => {
    if (!props.field.options) return [];
    if (Array.isArray(props.field.options)) return props.field.options as string[];
    if ('items' in props.field.options && Array.isArray(props.field.options.items)) {
        return props.field.options.items as string[];
    }
    return Object.values(props.field.options).filter((v) => typeof v === 'string') as string[];
});
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

        <Select
            v-else
            :model-value="value ?? undefined"
            @update:model-value="emit('update:value', String($event))"
        >
            <SelectTrigger class="h-full w-full rounded-none border-0 shadow-none focus-visible:ring-0">
                <SelectValue :placeholder="field.placeholder ?? 'Select...'" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="item in items"
                    :key="item"
                    :value="item"
                >
                    {{ item }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
