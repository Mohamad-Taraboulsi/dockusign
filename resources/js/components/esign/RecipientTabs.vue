<script setup lang="ts">
import { computed } from 'vue';
import type { DocumentRecipient } from '@/types/esign';

const props = defineProps<{
    recipients: DocumentRecipient[];
    selectedId: string | null;
    colors: string[];
}>();

const emit = defineEmits<{
    select: [recipientId: string];
}>();

function getColor(index: number): string {
    return props.colors[index % props.colors.length] ?? '#3B82F6';
}

function displayName(recipient: DocumentRecipient): string {
    return recipient.name || recipient.email;
}

function initial(recipient: DocumentRecipient): string {
    return displayName(recipient).charAt(0).toUpperCase();
}
</script>

<template>
    <div class="flex gap-1 overflow-x-auto border-b pb-1">
        <button
            v-for="(recipient, index) in recipients"
            :key="recipient.id"
            type="button"
            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
            :class="[
                selectedId === recipient.id
                    ? 'text-white shadow-sm'
                    : 'hover:bg-muted/50',
            ]"
            :style="{
                backgroundColor: selectedId === recipient.id ? getColor(index) : 'transparent',
                color: selectedId === recipient.id ? '#fff' : getColor(index),
                borderColor: getColor(index),
            }"
            @click="emit('select', recipient.id)"
        >
            <span
                class="flex size-5 items-center justify-center rounded-full text-[10px] font-bold"
                :style="{
                    backgroundColor: selectedId === recipient.id ? 'rgba(255,255,255,0.25)' : getColor(index),
                    color: '#fff',
                }"
            >
                {{ initial(recipient) }}
            </span>
            <span class="max-w-[120px] truncate">{{ displayName(recipient) }}</span>
        </button>
    </div>
</template>
