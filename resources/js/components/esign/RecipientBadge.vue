<script setup lang="ts">
import { computed } from 'vue';
import type { DocumentRecipient } from '@/types/esign';

const props = defineProps<{
    recipient: DocumentRecipient;
    color: string;
}>();

const displayName = computed(() => props.recipient.name || props.recipient.email);

const initial = computed(() => displayName.value.charAt(0).toUpperCase());
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium"
        :style="{ borderColor: color, color }"
    >
        <span
            class="flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
            :style="{ backgroundColor: color }"
        >
            {{ initial }}
        </span>
        <span class="max-w-[140px] truncate">{{ displayName }}</span>
    </span>
</template>
