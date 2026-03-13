<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import SigningLayout from '@/layouts/SigningLayout.vue';

defineProps<{
    documentTitle: string;
}>();
</script>

<template>
    <Head :title="`Signed - ${documentTitle}`" />

    <SigningLayout :document-title="documentTitle">
        <div class="flex flex-1 items-center justify-center p-6">
            <div class="flex max-w-md flex-col items-center gap-6 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <CheckCircle2 class="h-10 w-10 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold">Signing Complete</h1>
                    <p class="mt-2 text-muted-foreground">
                        You have successfully signed <strong>{{ documentTitle }}</strong>.
                        You'll receive a notification when all parties have signed.
                    </p>
                </div>
                <Link href="/dashboard">
                    <Button>Go to Dashboard</Button>
                </Link>
            </div>
        </div>
    </SigningLayout>
</template>
