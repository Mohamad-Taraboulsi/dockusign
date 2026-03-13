<script setup lang="ts">
import { ArrowDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { DocumentField } from '@/types/esign';

const props = withDefaults(
    defineProps<{
        fields: DocumentField[];
        currentFieldId: string | null;
    }>(),
    {
        currentFieldId: null,
    },
);

const emit = defineEmits<{
    navigate: [fieldId: string];
}>();

const requiredUnfilledFields = computed(() => {
    return props.fields
        .filter((f) => f.is_required && !f.value?.value && !f.value?.file_path)
        .sort((a, b) => {
            if (a.page_number !== b.page_number) return a.page_number - b.page_number;
            if (a.position_y !== b.position_y) return a.position_y - b.position_y;
            return a.position_x - b.position_x;
        });
});

const nextField = computed(() => {
    const fields = requiredUnfilledFields.value;
    if (fields.length === 0) return null;

    if (!props.currentFieldId) return fields[0];

    const currentIndex = fields.findIndex((f) => f.id === props.currentFieldId);
    if (currentIndex === -1 || currentIndex === fields.length - 1) {
        return fields[0];
    }
    return fields[currentIndex + 1];
});

const remainingCount = computed(() => requiredUnfilledFields.value.length);

function navigateNext() {
    if (nextField.value) {
        emit('navigate', nextField.value.id);
    }
}
</script>

<template>
    <div v-if="remainingCount > 0" class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2">
        <Button
            size="lg"
            class="shadow-lg"
            @click="navigateNext"
        >
            <ArrowDown class="size-4" />
            Next required field
            <span class="bg-primary-foreground/20 ml-1 rounded-full px-2 py-0.5 text-xs">
                {{ remainingCount }}
            </span>
        </Button>
    </div>
</template>
