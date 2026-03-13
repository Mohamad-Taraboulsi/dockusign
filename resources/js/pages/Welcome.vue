<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, FileSignature, Shield, Zap } from 'lucide-vue-next';
import { Toaster } from 'vue-sonner';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useFlashToast } from '@/composables/useFlashToast';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

useFlashToast();
</script>

<template>
    <Head title="Dockusign - Electronic Signatures" />
    <Toaster position="top-right" :duration="4000" rich-colors close-button />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900">
        <!-- Nav -->
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md shadow-indigo-500/20">
                    <AppLogoIcon class="size-5 text-white" />
                </div>
                <span class="text-lg font-bold tracking-tight">Dockusign</span>
            </div>
            <nav class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                >
                    <Button size="sm">Dashboard</Button>
                </Link>
                <template v-else>
                    <Link :href="login()">
                        <Button variant="ghost" size="sm">Sign in</Button>
                    </Link>
                    <Link v-if="canRegister" :href="register()">
                        <Button size="sm">Get started</Button>
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Hero -->
        <section class="mx-auto max-w-6xl px-6 pb-24 pt-20 text-center lg:pt-28">
            <div class="mx-auto max-w-2xl">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border bg-white px-4 py-1.5 text-xs font-medium text-muted-foreground shadow-sm dark:bg-slate-800">
                    <span class="inline-block size-1.5 rounded-full bg-green-500" />
                    Open-source eSignature platform
                </div>

                <h1 class="text-4xl font-extrabold tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                    Sign documents<br />
                    <span class="bg-gradient-to-r from-indigo-500 to-violet-600 bg-clip-text text-transparent">
                        with confidence
                    </span>
                </h1>

                <p class="mx-auto mt-6 max-w-lg text-lg text-muted-foreground">
                    Upload, prepare, and send documents for electronic signatures.
                    Track every step in real-time.
                </p>

                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link v-if="!$page.props.auth.user" :href="register()">
                        <Button size="lg" class="px-8 shadow-md shadow-indigo-500/20">
                            Start for free
                        </Button>
                    </Link>
                    <Link v-else :href="dashboard()">
                        <Button size="lg" class="px-8 shadow-md shadow-indigo-500/20">
                            Go to Dashboard
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Feature illustration -->
            <div class="relative mx-auto mt-20 max-w-3xl">
                <div class="absolute -inset-4 rounded-2xl bg-gradient-to-r from-indigo-500/10 via-violet-500/10 to-purple-500/10 blur-2xl" />
                <div class="relative overflow-hidden rounded-xl border bg-card shadow-2xl shadow-indigo-500/5">
                    <div class="flex items-center gap-2 border-b bg-muted/50 px-4 py-2.5">
                        <div class="flex gap-1.5">
                            <span class="size-3 rounded-full bg-red-400/80" />
                            <span class="size-3 rounded-full bg-amber-400/80" />
                            <span class="size-3 rounded-full bg-green-400/80" />
                        </div>
                        <span class="mx-auto text-xs text-muted-foreground">Document Editor</span>
                    </div>
                    <div class="grid grid-cols-4 divide-x">
                        <div class="col-span-1 space-y-3 bg-muted/30 p-4">
                            <div class="text-xs font-medium text-muted-foreground">Fields</div>
                            <div v-for="field in ['Signature', 'Initials', 'Date', 'Text']" :key="field"
                                class="flex items-center gap-2 rounded-md border bg-card px-2.5 py-1.5 text-xs"
                            >
                                <FileSignature class="size-3 text-indigo-500" />
                                {{ field }}
                            </div>
                        </div>
                        <div class="col-span-3 flex items-center justify-center bg-slate-50/50 p-12 dark:bg-slate-900/30">
                            <div class="w-full max-w-xs space-y-3 rounded-lg border-2 border-dashed border-indigo-300/40 bg-white p-6 dark:bg-slate-800">
                                <div class="h-2 w-3/4 rounded bg-slate-200 dark:bg-slate-700" />
                                <div class="h-2 w-full rounded bg-slate-200 dark:bg-slate-700" />
                                <div class="h-2 w-2/3 rounded bg-slate-200 dark:bg-slate-700" />
                                <div class="mt-4 h-8 w-1/2 rounded border-2 border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="border-t bg-muted/30 py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="mb-12 text-center text-2xl font-bold">Everything you need</h2>
                <div class="grid gap-8 sm:grid-cols-3">
                    <div class="rounded-xl border bg-card p-6">
                        <div class="mb-4 flex size-10 items-center justify-center rounded-lg bg-indigo-500/10">
                            <Zap class="size-5 text-indigo-500" />
                        </div>
                        <h3 class="mb-2 font-semibold">Drag & Drop Editor</h3>
                        <p class="text-sm text-muted-foreground">
                            Place signature fields, text inputs, checkboxes and more
                            directly on your documents with an intuitive editor.
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-6">
                        <div class="mb-4 flex size-10 items-center justify-center rounded-lg bg-green-500/10">
                            <CheckCircle2 class="size-5 text-green-500" />
                        </div>
                        <h3 class="mb-2 font-semibold">Real-time Tracking</h3>
                        <p class="text-sm text-muted-foreground">
                            Monitor document status, recipient activity, and completion
                            progress from your dashboard.
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-6">
                        <div class="mb-4 flex size-10 items-center justify-center rounded-lg bg-violet-500/10">
                            <Shield class="size-5 text-violet-500" />
                        </div>
                        <h3 class="mb-2 font-semibold">AI-Powered Analysis</h3>
                        <p class="text-sm text-muted-foreground">
                            Built-in legal clause detection and document translation
                            help signers understand what they are signing.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t py-8">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <div class="flex size-6 items-center justify-center rounded bg-gradient-to-br from-indigo-500 to-violet-600">
                        <AppLogoIcon class="size-3.5 text-white" />
                    </div>
                    Dockusign
                </div>
                <p class="text-xs text-muted-foreground">Built with Laravel & Vue</p>
            </div>
        </footer>
    </div>
</template>
