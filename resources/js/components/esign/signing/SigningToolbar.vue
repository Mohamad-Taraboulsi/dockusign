<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    documentTitle: string;
    progress: number;
    totalFields: number;
    filledFields: number;
}>();

const emit = defineEmits<{
    submit: [];
    decline: [];
}>();

const clampedProgress = computed(() => Math.min(100, Math.max(0, props.progress)));
</script>

<template>
    <div class="bg-background flex items-center justify-between gap-4 border-b px-4 py-3 shadow-sm">
        <!-- Left: document title -->
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-sm font-semibold">{{ documentTitle }}</h1>
        </div>

        <!-- Center: progress -->
        <div class="flex items-center gap-3">
            <div class="bg-muted h-2 w-40 overflow-hidden rounded-full">
                <div
                    class="bg-primary h-full rounded-full transition-all duration-300"
                    :style="{ width: `${clampedProgress}%` }"
                />
            </div>
            <span class="text-muted-foreground whitespace-nowrap text-xs">
                {{ filledFields }} of {{ totalFields }} fields completed
            </span>
        </div>

        <!-- Right: actions -->
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="emit('decline')">
                Decline
            </Button>
            <Button
                size="sm"
                :disabled="filledFields < totalFields"
                @click="emit('submit')"
            >
                Submit
            </Button>
        </div>
    </div>
</template>
