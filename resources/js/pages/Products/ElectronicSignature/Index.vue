<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSignature, Plus, Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Document, DocumentStatus } from '@/types/esign';
import { STATUS_LABELS } from '@/types/esign';

const props = defineProps<{
    documents: {
        data: Document[];
        links: Record<string, string | null>[];
        current_page: number;
        last_page: number;
    };
    tab: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'eSign', href: '/esign/documents' },
];

const search = ref('');
const activeTab = ref(props.tab);

const tabs = [
    { key: 'sent', label: 'Sent' },
    { key: 'received', label: 'Received' },
    { key: 'drafts', label: 'Drafts' },
];

function switchTab(tab: string) {
    activeTab.value = tab;
    router.get('/esign/documents', { tab }, { preserveState: true });
}

function statusVariant(
    status: DocumentStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'declined':
        case 'voided':
            return 'destructive';
        case 'draft':
            return 'outline';
        default:
            return 'secondary';
    }
}

function formatDate(date: string) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function getDocumentLink(doc: Document): string {
    const recipient = doc.recipients?.[0];
    if (recipient && activeTab.value === 'received') {
        return `/sign/${recipient.id}`;
    }
    return doc.status === 'draft'
        ? `/esign/documents/${doc.id}/editor`
        : `/esign/documents/${doc.id}`;
}
</script>

<template>
    <Head title="Documents - eSign" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <FileSignature class="h-6 w-6 text-primary" />
                    <h1 class="text-2xl font-semibold">Documents</h1>
                </div>
                <Link href="/esign/documents/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Document
                    </Button>
                </Link>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 rounded-lg border bg-muted/50 p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="rounded-md px-4 py-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Document List -->
            <div
                v-if="documents.data.length === 0"
                class="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed p-12"
            >
                <FileSignature class="h-12 w-12 text-muted-foreground/50" />
                <div class="text-center">
                    <p class="text-lg font-medium">No documents yet</p>
                    <p class="text-sm text-muted-foreground">
                        Create your first document to get started.
                    </p>
                </div>
                <Link href="/esign/documents/create">
                    <Button variant="outline">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Document
                    </Button>
                </Link>
            </div>

            <div v-else class="flex flex-col gap-2">
                <Link
                    v-for="doc in documents.data"
                    :key="doc.id"
                    :href="getDocumentLink(doc)"
                    class="flex items-center gap-4 rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <FileSignature class="h-5 w-5 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ doc.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ doc.recipients?.length ?? 0 }} recipient{{
                                (doc.recipients?.length ?? 0) !== 1 ? 's' : ''
                            }}
                            &middot;
                            {{ doc.files?.length ?? 0 }} file{{
                                (doc.files?.length ?? 0) !== 1 ? 's' : ''
                            }}
                        </p>
                    </div>
                    <Badge :variant="statusVariant(doc.status)">
                        {{ STATUS_LABELS[doc.status] }}
                    </Badge>
                    <span class="text-sm text-muted-foreground">
                        {{ formatDate(doc.created_at) }}
                    </span>
                </Link>
            </div>

            <!-- Pagination -->
            <div
                v-if="documents.last_page > 1"
                class="flex justify-center gap-2"
            >
                <template v-for="link in documents.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-1 text-sm transition-colors hover:bg-accent"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : ''
                        "
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
