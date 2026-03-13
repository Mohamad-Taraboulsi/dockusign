<script setup lang="ts">
import { Trash2, Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DocumentField } from '@/types/esign';
import { FIELD_TYPES } from '@/types/esign';

const props = withDefaults(
    defineProps<{
        field: DocumentField | null;
        recipientName: string;
    }>(),
    {
        recipientName: '',
    },
);

const emit = defineEmits<{
    update: [fieldId: string, changes: Partial<DocumentField>];
    delete: [fieldId: string];
}>();

const fieldTypeConfig = computed(() => {
    if (!props.field) return null;
    return FIELD_TYPES.find((ft) => ft.type === props.field!.type) ?? null;
});

const showOptionsEditor = computed(() => {
    return props.field?.type === 'dropdown' || props.field?.type === 'radio';
});

const localLabel = ref('');
const localPlaceholder = ref('');
const localRequired = ref(false);
const localOptions = ref<string[]>([]);

watch(
    () => props.field,
    (field) => {
        if (field) {
            localLabel.value = field.label ?? '';
            localPlaceholder.value = field.placeholder ?? '';
            localRequired.value = field.is_required;
            const opts = field.options as Record<string, unknown> | null;
            localOptions.value = Array.isArray(opts?.items)
                ? [...(opts.items as string[])]
                : [];
        }
    },
    { immediate: true },
);

function updateLabel(value: string | number) {
    if (!props.field) return;
    debouncedEmitUpdate(props.field.id, { label: String(value) || null });
}

function updatePlaceholder(value: string | number) {
    if (!props.field) return;
    debouncedEmitUpdate(props.field.id, { placeholder: String(value) || null });
}

const debouncedEmitUpdate = useDebounceFn(
    (fieldId: string, changes: Partial<DocumentField>) => {
        emit('update', fieldId, changes);
    },
    300,
);

function updateRequired(checked: boolean | 'indeterminate') {
    if (!props.field) return;
    emit('update', props.field.id, { is_required: checked === true });
}

function addOption() {
    localOptions.value.push('');
    debouncedEmitOptions();
}

function removeOption(index: number) {
    localOptions.value.splice(index, 1);
    debouncedEmitOptions();
}

function updateOption(index: number, value: string) {
    localOptions.value[index] = value;
    debouncedEmitOptions();
}

const debouncedEmitOptions = useDebounceFn(() => {
    if (!props.field) return;
    emit('update', props.field.id, {
        options: { items: [...localOptions.value] },
    });
}, 300);

function deleteField() {
    if (!props.field) return;
    emit('delete', props.field.id);
}
</script>

<template>
    <div class="flex flex-col gap-4 p-4">
        <template v-if="field">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold tracking-tight">
                    Field Properties
                </h3>
                <Button variant="ghost" size="icon-sm" @click="deleteField">
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </div>

            <!-- Type display -->
            <div class="flex flex-col gap-1">
                <Label class="text-xs text-muted-foreground">Type</Label>
                <p class="text-sm font-medium">
                    {{ fieldTypeConfig?.label ?? field.type }}
                </p>
            </div>

            <!-- Assigned recipient -->
            <div v-if="recipientName" class="flex flex-col gap-1">
                <Label class="text-xs text-muted-foreground">Assigned to</Label>
                <p class="text-sm">{{ recipientName }}</p>
            </div>

            <!-- Label -->
            <div class="flex flex-col gap-1.5">
                <Label for="field-label">Label</Label>
                <Input
                    id="field-label"
                    :model-value="localLabel"
                    placeholder="Field label"
                    @update:model-value="updateLabel"
                />
            </div>

            <!-- Placeholder -->
            <div class="flex flex-col gap-1.5">
                <Label for="field-placeholder">Placeholder</Label>
                <Input
                    id="field-placeholder"
                    :model-value="localPlaceholder"
                    placeholder="Placeholder text"
                    @update:model-value="updatePlaceholder"
                />
            </div>

            <!-- Required toggle -->
            <div class="flex items-center gap-2">
                <Checkbox
                    id="field-required"
                    :checked="localRequired"
                    @update:checked="updateRequired"
                />
                <Label for="field-required">Required</Label>
            </div>

            <!-- Options editor (for dropdown/radio) -->
            <div v-if="showOptionsEditor" class="flex flex-col gap-2">
                <Label>Options</Label>
                <div
                    v-for="(option, index) in localOptions"
                    :key="index"
                    class="flex items-center gap-1.5"
                >
                    <Input
                        :model-value="option"
                        :placeholder="`Option ${index + 1}`"
                        class="h-8 text-sm"
                        @update:model-value="
                            (val) => updateOption(index, String(val))
                        "
                    />
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        @click="removeOption(index)"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full"
                    @click="addOption"
                >
                    <Plus class="size-3.5" />
                    Add option
                </Button>
            </div>
        </template>

        <template v-else>
            <div
                class="flex flex-col items-center justify-center py-10 text-center text-sm text-muted-foreground"
            >
                <p>Select a field to edit its properties</p>
            </div>
        </template>
    </div>
</template>
