<script setup lang="ts">
import { Toaster } from 'vue-sonner';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useFlashToast } from '@/composables/useFlashToast';

defineProps<{
    documentTitle?: string;
}>();

useFlashToast();
</script>

<template>
    <div class="bg-background flex min-h-screen flex-col">
        <!-- Header -->
        <header class="bg-background flex items-center justify-center border-b px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-gradient-to-br from-indigo-500 to-violet-600">
                    <AppLogoIcon class="size-5 text-white" />
                </div>
                <span class="text-sm font-semibold">Dockusign</span>
                <span v-if="documentTitle" class="text-muted-foreground text-sm">
                    &mdash; {{ documentTitle }}
                </span>
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1">
            <slot />
        </main>
    </div>
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
