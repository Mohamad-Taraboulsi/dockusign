<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SigningLayout from '@/layouts/SigningLayout.vue';

const props = defineProps<{
    recipient: { id: string; email: string; name: string | null };
    documentTitle: string;
}>();

const form = useForm({
    access_code: '',
});

function submit() {
    form.post(`/sign/${props.recipient.id}/verify`);
}
</script>

<template>
    <Head :title="`Verify - ${documentTitle}`" />

    <SigningLayout :document-title="documentTitle">
        <div class="mx-auto w-full max-w-md">
            <div class="flex flex-col items-center gap-6 rounded-xl border bg-card p-8 shadow-sm">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                    <ShieldCheck class="h-7 w-7 text-primary" />
                </div>
                <div class="text-center">
                    <h1 class="text-xl font-semibold">Verify Your Identity</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Enter the access code sent to <strong>{{ recipient.email }}</strong> to continue.
                    </p>
                </div>
                <form @submit.prevent="submit" class="w-full space-y-4">
                    <div>
                        <Label for="access_code">Access Code</Label>
                        <Input
                            id="access_code"
                            v-model="form.access_code"
                            placeholder="Enter access code"
                            autofocus
                            class="mt-1 text-center text-lg tracking-widest"
                        />
                        <p v-if="form.errors.access_code" class="mt-1 text-sm text-destructive">
                            {{ form.errors.access_code }}
                        </p>
                    </div>
                    <Button type="submit" class="w-full" :disabled="form.processing">
                        {{ form.processing ? 'Verifying...' : 'Continue' }}
                    </Button>
                </form>
            </div>
        </div>
    </SigningLayout>
</template>
