<script setup lang="ts">
import { Toaster } from 'vue-sonner';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import { useFlashToast } from '@/composables/useFlashToast';

defineProps<{
    title?: string;
    description?: string;
}>();

useFlashToast();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
