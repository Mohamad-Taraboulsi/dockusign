<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { XCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import SigningLayout from '@/layouts/SigningLayout.vue';

defineProps<{
    documentTitle: string;
}>();
</script>

<template>
    <Head :title="`Declined - ${documentTitle}`" />

    <SigningLayout :document-title="documentTitle">
        <div class="flex flex-1 items-center justify-center p-6">
            <div class="flex max-w-md flex-col items-center gap-6 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <XCircle class="h-10 w-10 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold">Document Declined</h1>
                    <p class="mt-2 text-muted-foreground">
                        You have declined to sign <strong>{{ documentTitle }}</strong>.
                        The sender has been notified.
                    </p>
                </div>
                <Link href="/dashboard">
                    <Button variant="outline">Go to Dashboard</Button>
                </Link>
            </div>
        </div>
    </SigningLayout>
</template>
