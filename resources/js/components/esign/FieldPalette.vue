<script setup lang="ts">
import {
    PenTool,
    Type,
    Stamp,
    User,
    Briefcase,
    Mail,
    CheckSquare,
    ChevronDown,
    Circle,
    FileText,
    Paperclip,
    Calendar,
} from 'lucide-vue-next';
import { computed  } from 'vue';
import type {Component} from 'vue';
import type { FieldTypeConfig } from '@/types/esign';
import { FIELD_TYPES } from '@/types/esign';

const iconMap: Record<string, Component> = {
    PenTool,
    Type,
    Stamp,
    User,
    Briefcase,
    Mail,
    CheckSquare,
    ChevronDown,
    Circle,
    FileText,
    Paperclip,
    Calendar,
};

const groupLabels: Record<string, string> = {
    text: 'Text',
    elements: 'Elements',
    other: 'Other',
};

const groupOrder = ['text', 'elements', 'other'] as const;

const groupedFields = computed(() => {
    const groups: Record<string, FieldTypeConfig[]> = {};
    for (const ft of FIELD_TYPES) {
        if (!groups[ft.group]) {
            groups[ft.group] = [];
        }
        groups[ft.group].push(ft);
    }
    return groups;
});

function onDragStart(e: DragEvent, fieldType: string) {
    if (!e.dataTransfer) return;
    e.dataTransfer.setData('application/esign-field-type', fieldType);
    e.dataTransfer.effectAllowed = 'copy';
}
</script>

<template>
    <div class="flex flex-col gap-4 p-4">
        <h3 class="text-sm font-semibold tracking-tight">Fields</h3>

        <div v-for="group in groupOrder" :key="group" class="flex flex-col gap-2">
            <h4 class="text-muted-foreground text-xs font-medium uppercase tracking-wider">
                {{ groupLabels[group] }}
            </h4>

            <div class="grid grid-cols-2 gap-1.5">
                <div
                    v-for="ft in groupedFields[group]"
                    :key="ft.type"
                    class="field-palette-item bg-background hover:bg-accent flex cursor-grab items-center gap-2 rounded-md border px-2 py-1.5 text-xs font-medium transition-colors active:cursor-grabbing"
                    draggable="true"
                    :data-field-type="ft.type"
                    @dragstart="onDragStart($event, ft.type)"
                >
                    <component
                        :is="iconMap[ft.icon]"
                        class="text-muted-foreground size-3.5 shrink-0"
                    />
                    <span class="truncate">{{ ft.label }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
