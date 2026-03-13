<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    FileSignature,
    Users,
    CheckCircle2,
    Clock,
    TrendingUp,
    Timer,
    Send,
    Eye,
    XCircle,
    Ban,
    ArrowRight,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type DashboardStats = {
    totalDocuments: number;
    totalRecipients: number;
    completedCount: number;
    awaitingAction: number;
    completionRate: number;
    avgCompletionHours: number | null;
    statusCounts: Record<string, number>;
    recipientStatusCounts: Record<string, number>;
    monthlyDocuments: { month: string; total: number; completed: number; drafts: number }[];
    recentActivity: {
        id: number;
        type: string;
        description: string;
        document_title: string;
        document_id: string;
        recipient_name: string | null;
        created_at: string;
    }[];
    recentDocuments: {
        id: string;
        title: string;
        status: string;
        created_at: string;
        completed_at: string | null;
        recipients: { id: string; name: string; status: string }[];
    }[];
};

const props = defineProps<{ stats: DashboardStats }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard() }];

// --- Chart helpers ---

const STATUS_COLORS: Record<string, string> = {
    draft: '#94a3b8',
    sent: '#6366f1',
    in_progress: '#f59e0b',
    completed: '#22c55e',
    declined: '#ef4444',
    voided: '#6b7280',
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    sent: 'Sent',
    in_progress: 'In Progress',
    completed: 'Completed',
    declined: 'Declined',
    voided: 'Voided',
};

const RECIPIENT_STATUS_COLORS: Record<string, string> = {
    pending: '#94a3b8',
    sent: '#6366f1',
    opened: '#8b5cf6',
    signed: '#22c55e',
    declined: '#ef4444',
};

const RECIPIENT_STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    sent: 'Sent',
    opened: 'Opened',
    signed: 'Signed',
    declined: 'Declined',
};

// Donut chart data
const donutSegments = computed(() => {
    const entries = Object.entries(props.stats.statusCounts);
    const total = entries.reduce((sum, [, count]) => sum + count, 0);
    if (total === 0) return [];

    let cumulative = 0;
    return entries.map(([status, count]) => {
        const pct = (count / total) * 100;
        const offset = cumulative;
        cumulative += pct;
        return { status, count, pct, offset, color: STATUS_COLORS[status] || '#94a3b8' };
    });
});

// Bar chart data (monthly trend)
const barChart = computed(() => {
    const data = props.stats.monthlyDocuments;
    if (data.length === 0) return { bars: [], maxVal: 0 };

    const maxVal = Math.max(...data.map((d) => d.total), 1);
    const bars = data.map((d) => ({
        label: formatMonthLabel(d.month),
        total: d.total,
        completed: d.completed,
        drafts: d.drafts,
        totalPct: (d.total / maxVal) * 100,
        completedPct: (d.completed / maxVal) * 100,
    }));

    return { bars, maxVal };
});

function formatMonthLabel(ym: string): string {
    const [year, month] = ym.split('-');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[parseInt(month) - 1]} '${year.slice(2)}`;
}

function activityIcon(type: string) {
    switch (type) {
        case 'created': return FileSignature;
        case 'sent': return Send;
        case 'opened': return Eye;
        case 'signed': return CheckCircle2;
        case 'completed': return CheckCircle2;
        case 'declined': return XCircle;
        case 'voided': return Ban;
        default: return Clock;
    }
}

function activityColor(type: string): string {
    switch (type) {
        case 'signed':
        case 'completed': return 'text-green-500';
        case 'declined':
        case 'voided': return 'text-red-500';
        case 'sent': return 'text-indigo-500';
        case 'opened': return 'text-violet-500';
        default: return 'text-muted-foreground';
    }
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
        case 'signed': return 'default';
        case 'declined':
        case 'voided': return 'destructive';
        case 'draft':
        case 'pending': return 'outline';
        default: return 'secondary';
    }
}

function timeAgo(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="flex items-center gap-4 p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10">
                            <FileSignature class="h-6 w-6 text-indigo-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Total Documents</p>
                            <p class="text-2xl font-bold">{{ stats.totalDocuments }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="flex items-center gap-4 p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-green-500/10">
                            <CheckCircle2 class="h-6 w-6 text-green-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Completed</p>
                            <p class="text-2xl font-bold">{{ stats.completedCount }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="flex items-center gap-4 p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-amber-500/10">
                            <Clock class="h-6 w-6 text-amber-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Awaiting Action</p>
                            <p class="text-2xl font-bold">{{ stats.awaitingAction }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="flex items-center gap-4 p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-violet-500/10">
                            <Users class="h-6 w-6 text-violet-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Total Recipients</p>
                            <p class="text-2xl font-bold">{{ stats.totalRecipients }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Metrics row -->
            <div class="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardContent class="flex items-center gap-4 p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10">
                            <TrendingUp class="h-6 w-6 text-emerald-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Completion Rate</p>
                            <p class="text-2xl font-bold">{{ stats.completionRate }}%</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="flex items-center gap-4 p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-cyan-500/10">
                            <Timer class="h-6 w-6 text-cyan-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Avg. Completion Time</p>
                            <p class="text-2xl font-bold">
                                <template v-if="stats.avgCompletionHours !== null">
                                    {{ stats.avgCompletionHours < 24
                                        ? `${stats.avgCompletionHours}h`
                                        : `${(stats.avgCompletionHours / 24).toFixed(1)}d`
                                    }}
                                </template>
                                <span v-else class="text-muted-foreground">--</span>
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts row -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Document Status Breakdown (Donut) -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Document Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="stats.totalDocuments === 0" class="flex h-48 items-center justify-center text-sm text-muted-foreground">
                            No documents yet
                        </div>
                        <div v-else class="flex items-center gap-8">
                            <!-- SVG Donut -->
                            <div class="relative shrink-0">
                                <svg width="160" height="160" viewBox="0 0 42 42">
                                    <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="0" />
                                    <circle
                                        v-for="seg in donutSegments"
                                        :key="seg.status"
                                        cx="21"
                                        cy="21"
                                        r="15.915"
                                        fill="none"
                                        :stroke="seg.color"
                                        stroke-width="5"
                                        :stroke-dasharray="`${seg.pct} ${100 - seg.pct}`"
                                        :stroke-dashoffset="25 - seg.offset"
                                        stroke-linecap="round"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-2xl font-bold">{{ stats.totalDocuments }}</span>
                                    <span class="text-xs text-muted-foreground">total</span>
                                </div>
                            </div>
                            <!-- Legend -->
                            <div class="space-y-2">
                                <div v-for="seg in donutSegments" :key="seg.status" class="flex items-center gap-2 text-sm">
                                    <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: seg.color }" />
                                    <span class="text-muted-foreground">{{ STATUS_LABELS[seg.status] || seg.status }}</span>
                                    <span class="ml-auto font-medium">{{ seg.count }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recipient Status Breakdown -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Recipient Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="stats.totalRecipients === 0" class="flex h-48 items-center justify-center text-sm text-muted-foreground">
                            No recipients yet
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(label, status) in RECIPIENT_STATUS_LABELS"
                                :key="status"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ label }}</span>
                                    <span class="font-medium">{{ stats.recipientStatusCounts[status] || 0 }}</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{
                                            width: `${stats.totalRecipients > 0
                                                ? ((stats.recipientStatusCounts[status] || 0) / stats.totalRecipients) * 100
                                                : 0}%`,
                                            backgroundColor: RECIPIENT_STATUS_COLORS[status],
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Monthly Trend Bar Chart -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Monthly Activity (Last 6 Months)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="barChart.bars.length === 0" class="flex h-48 items-center justify-center text-sm text-muted-foreground">
                        No data yet
                    </div>
                    <div v-else class="space-y-4">
                        <!-- Bars -->
                        <div class="flex items-end gap-3" style="height: 180px;">
                            <div
                                v-for="bar in barChart.bars"
                                :key="bar.label"
                                class="flex flex-1 flex-col items-center gap-1"
                                style="height: 100%;"
                            >
                                <div class="flex w-full flex-1 items-end justify-center gap-0.5">
                                    <!-- Total bar -->
                                    <div
                                        class="w-5 rounded-t bg-indigo-500/80 transition-all duration-500"
                                        :style="{ height: `${Math.max(bar.totalPct, 4)}%` }"
                                        :title="`Total: ${bar.total}`"
                                    />
                                    <!-- Completed bar -->
                                    <div
                                        class="w-5 rounded-t bg-green-500/80 transition-all duration-500"
                                        :style="{ height: `${Math.max(bar.completedPct, bar.completed > 0 ? 4 : 0)}%` }"
                                        :title="`Completed: ${bar.completed}`"
                                    />
                                </div>
                                <span class="text-xs text-muted-foreground">{{ bar.label }}</span>
                            </div>
                        </div>
                        <!-- Legend -->
                        <div class="flex items-center justify-center gap-6 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-2.5 rounded-sm bg-indigo-500/80" />
                                Total
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-2.5 rounded-sm bg-green-500/80" />
                                Completed
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Bottom row: Recent Documents + Activity Feed -->
            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Recent Documents -->
                <Card class="lg:col-span-3">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Recent Documents</CardTitle>
                        <a href="/esign/documents" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                            View all <ArrowRight class="h-3 w-3" />
                        </a>
                    </CardHeader>
                    <CardContent>
                        <div v-if="stats.recentDocuments.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No documents yet. Start by creating one.
                        </div>
                        <div v-else class="divide-y">
                            <div v-for="doc in stats.recentDocuments" :key="doc.id" class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <FileSignature class="h-4 w-4 text-muted-foreground" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a :href="`/esign/documents/${doc.id}`" class="truncate text-sm font-medium hover:underline">
                                        {{ doc.title }}
                                    </a>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>{{ new Date(doc.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</span>
                                        <span>&middot;</span>
                                        <span>{{ doc.recipients.length }} recipient{{ doc.recipients.length !== 1 ? 's' : '' }}</span>
                                    </div>
                                </div>
                                <Badge :variant="statusVariant(doc.status)" class="shrink-0">
                                    {{ STATUS_LABELS[doc.status] || doc.status }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Activity Feed -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base">Recent Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="stats.recentActivity.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No activity yet
                        </div>
                        <div v-else class="relative space-y-0">
                            <div class="absolute bottom-0 left-3 top-0 w-px bg-border" />
                            <div
                                v-for="activity in stats.recentActivity"
                                :key="activity.id"
                                class="relative flex gap-3 pb-4 last:pb-0"
                            >
                                <div class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-card ring-2 ring-border">
                                    <component :is="activityIcon(activity.type)" class="h-3 w-3" :class="activityColor(activity.type)" />
                                </div>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <p class="truncate text-sm">{{ activity.description }}</p>
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <span>{{ timeAgo(activity.created_at) }}</span>
                                        <template v-if="activity.document_title">
                                            <span>&middot;</span>
                                            <a :href="`/esign/documents/${activity.document_id}`" class="truncate hover:underline">
                                                {{ activity.document_title }}
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
