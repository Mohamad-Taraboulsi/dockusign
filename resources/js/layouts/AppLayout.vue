<script setup lang="ts">
import { Toaster } from 'vue-sonner';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

useFlashToast();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
