<script setup lang="ts">
import { computed } from 'vue';
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

const groupName = computed(() => `radio-${props.field.id}`);

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
        class="relative flex flex-col gap-1 rounded-md border-2 p-1.5"
        :style="{ borderColor: color }"
    >
        <span
            v-if="field.label"
            class="text-[10px] leading-tight font-medium"
            :style="{ color }"
        >
            {{ field.label }}
            <span v-if="field.is_required" class="text-red-500">*</span>
        </span>

        <label
            v-for="item in items"
            :key="item"
            class="flex items-center gap-1.5 text-xs"
            :class="{ 'pointer-events-none opacity-70': readonly }"
        >
            <input
                type="radio"
                :name="groupName"
                :value="item"
                :checked="value === item"
                :disabled="readonly"
                class="accent-current size-3.5"
                :style="{ accentColor: color }"
                @change="emit('update:value', item)"
            />
            {{ item }}
        </label>
    </div>
</template>
