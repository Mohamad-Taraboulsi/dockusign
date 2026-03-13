<script setup lang="ts">
import {
    Shield,
    Languages,
    AlertTriangle,
    Info,
    X,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';

interface LegalFinding {
    text: string;
    category: string;
    category_label: string;
    description: string;
    score: number;
    level: 'high' | 'medium' | 'low';
}

interface LegalAnalysisResult {
    findings: LegalFinding[];
    translated: boolean;
    translated_text: string | null;
    paragraphs_analyzed: number;
}

interface TranslationResult {
    translated_text: string;
    original_text: string;
}

const props = defineProps<{
    recipientId: string;
    documentText: string | null;
}>();

const panelOpen = ref(false);
const analyzingLegal = ref(false);
const translating = ref(false);
const legalResult = ref<LegalAnalysisResult | null>(null);
const translationResult = ref<TranslationResult | null>(null);
const error = ref<string | null>(null);
const activeTab = ref<'legal' | 'translate'>('legal');

const highRiskCount = computed(
    () =>
        legalResult.value?.findings.filter((f) => f.level === 'high').length ??
        0,
);
const mediumRiskCount = computed(
    () =>
        legalResult.value?.findings.filter((f) => f.level === 'medium')
            .length ?? 0,
);

/**
 * Client-side English detection.
 * Checks ASCII letter ratio and common English stop words.
 */
const isDocumentEnglish = computed(() => {
    const text = props.documentText;
    if (!text || text.length < 20) return true; // assume English if too short to tell

    const sample = text.substring(0, 2000);

    // Count ASCII letters vs all unicode letters
    const asciiMatches = sample.match(/[a-zA-Z]/g);
    const allLetterMatches = sample.match(/\p{L}/gu);

    const asciiCount = asciiMatches?.length ?? 0;
    const allCount = allLetterMatches?.length ?? 0;

    if (allCount === 0) return true;

    const asciiRatio = asciiCount / allCount;

    // Check for common English stop words
    const lower = sample.toLowerCase();
    const stopWords = ['the', 'and', 'for', 'that', 'this', 'with', 'are', 'from', 'have', 'will', 'shall', 'not'];
    let stopWordCount = 0;
    for (const word of stopWords) {
        const regex = new RegExp(`\\b${word}\\b`, 'g');
        const matches = lower.match(regex);
        stopWordCount += matches?.length ?? 0;
    }

    return asciiRatio > 0.85 && stopWordCount > 3;
});

const showTranslateButton = computed(() => !isDocumentEnglish.value);

async function analyzeLegal() {
    if (!props.documentText) return;
    analyzingLegal.value = true;
    error.value = null;
    activeTab.value = 'legal';

    try {
        const response = await axios.post(
            `/sign/${props.recipientId}/ai/legal-analysis`,
            { text: props.documentText },
        );

        legalResult.value = response.data;

        // If the backend auto-translated, also store that
        if (
            legalResult.value?.translated &&
            legalResult.value?.translated_text
        ) {
            translationResult.value = {
                translated_text: legalResult.value.translated_text,
                original_text: props.documentText,
            };
        }

        panelOpen.value = true;
    } catch (e) {
        const err = e as any;
        error.value =
            err.response?.data?.message || 'Failed to analyze document.';
    } finally {
        analyzingLegal.value = false;
    }
}

async function translateDocument() {
    if (!props.documentText) return;
    translating.value = true;
    error.value = null;
    activeTab.value = 'translate';

    try {
        const response = await axios.post(
            `/sign/${props.recipientId}/ai/translate`,
            { text: props.documentText },
        );

        translationResult.value = response.data;
        panelOpen.value = true;
    } catch (e) {
        const err = e as any;
        error.value =
            err.response?.data?.message || 'Failed to translate document.';
    } finally {
        translating.value = false;
    }
}

function levelColor(level: string) {
    switch (level) {
        case 'high':
            return 'border-red-500 bg-red-50 dark:bg-red-950/30';
        case 'medium':
            return 'border-amber-500 bg-amber-50 dark:bg-amber-950/30';
        default:
            return 'border-blue-500 bg-blue-50 dark:bg-blue-950/30';
    }
}

function levelBadge(level: string) {
    switch (level) {
        case 'high':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'medium':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200';
        default:
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    }
}
</script>

<template>
    <!-- Floating AI toolbar -->
    <div class="fixed right-6 bottom-6 z-30 flex flex-col items-end gap-3">
        <!-- Error toast -->
        <div
            v-if="error"
            class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700 shadow-lg dark:border-red-800 dark:bg-red-950 dark:text-red-300"
        >
            <AlertTriangle class="h-4 w-4 shrink-0" />
            <span>{{ error }}</span>
            <button class="ml-2" @click="error = null">
                <X class="h-3 w-3" />
            </button>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-2">
            <Button
                v-if="showTranslateButton"
                variant="outline"
                size="sm"
                class="shadow-lg"
                :disabled="analyzingLegal || translating || !documentText"
                @click="translateDocument"
            >
                <Languages class="mr-2 h-4 w-4" />
                {{ translating ? 'Translating...' : 'Translate to English' }}
            </Button>
            <Button
                size="sm"
                class="shadow-lg"
                :disabled="analyzingLegal || translating || !documentText"
                @click="analyzeLegal"
            >
                <Shield class="mr-2 h-4 w-4" />
                {{ analyzingLegal ? 'Analyzing...' : 'AI Legal Check' }}
            </Button>
        </div>
    </div>

    <!-- Results panel -->
    <div
        v-if="panelOpen && (legalResult || translationResult)"
        class="fixed right-6 bottom-20 z-30 max-h-[70vh] w-[480px] overflow-hidden rounded-xl border bg-card shadow-2xl"
    >
        <!-- Panel header -->
        <div class="flex items-center justify-between border-b px-4 py-3">
            <div class="flex gap-1">
                <button
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'legal'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="activeTab = 'legal'"
                >
                    <Shield class="mr-1.5 inline h-3.5 w-3.5" />
                    Legal Analysis
                    <span v-if="legalResult" class="ml-1 text-xs opacity-75"
                        >({{ legalResult.findings.length }})</span
                    >
                </button>
                <button
                    v-if="translationResult"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'translate'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="activeTab = 'translate'"
                >
                    <Languages class="mr-1.5 inline h-3.5 w-3.5" />
                    Translation
                </button>
            </div>
            <button
                class="rounded p-1 hover:bg-muted"
                @click="panelOpen = false"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Legal Analysis Tab -->
        <div
            v-if="activeTab === 'legal'"
            class="max-h-[calc(70vh-60px)] overflow-y-auto p-4"
        >
            <template v-if="legalResult">
                <!-- Auto-translation notice -->
                <div
                    v-if="legalResult.translated"
                    class="mb-3 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300"
                >
                    <Languages class="h-3.5 w-3.5 shrink-0" />
                    <span
                        >Non-English document detected. Auto-translated before
                        analysis.</span
                    >
                </div>

                <!-- Summary -->
                <div class="mb-4 rounded-lg bg-muted/50 p-3">
                    <p class="text-sm font-medium">Analysis Summary</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Analyzed
                        {{ legalResult.paragraphs_analyzed }} paragraphs using
                        zero-shot classification.
                    </p>
                    <div class="mt-2 flex gap-3 text-xs">
                        <span
                            v-if="highRiskCount > 0"
                            class="flex items-center gap-1 font-medium text-red-600"
                        >
                            <AlertTriangle class="h-3 w-3" />
                            {{ highRiskCount }} high risk
                        </span>
                        <span
                            v-if="mediumRiskCount > 0"
                            class="flex items-center gap-1 font-medium text-amber-600"
                        >
                            <AlertTriangle class="h-3 w-3" />
                            {{ mediumRiskCount }} medium risk
                        </span>
                        <span
                            v-if="legalResult.findings.length === 0"
                            class="text-green-600"
                        >
                            No significant risks detected.
                        </span>
                    </div>
                </div>

                <!-- Findings -->
                <div class="space-y-3">
                    <div
                        v-for="(finding, idx) in legalResult.findings"
                        :key="idx"
                        class="rounded-lg border-l-4 p-3"
                        :class="levelColor(finding.level)"
                    >
                        <div class="mb-1.5 flex items-center gap-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                :class="levelBadge(finding.level)"
                            >
                                {{ finding.level }}
                            </span>
                            <span class="text-xs font-semibold">{{
                                finding.category_label
                            }}</span>
                            <span
                                class="ml-auto text-[10px] text-muted-foreground"
                            >
                                {{ Math.round(finding.score * 100) }}% match
                            </span>
                        </div>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ finding.description }}
                        </p>
                        <details class="mt-2">
                            <summary
                                class="cursor-pointer text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Show excerpt
                            </summary>
                            <p
                                class="mt-1.5 rounded bg-background/50 p-2 text-xs leading-relaxed italic"
                            >
                                "{{ finding.text.substring(0, 300)
                                }}{{ finding.text.length > 300 ? '...' : '' }}"
                            </p>
                        </details>
                    </div>
                </div>

                <!-- Disclaimer -->
                <div
                    class="mt-4 flex items-start gap-2 rounded-lg bg-muted/30 p-3 text-[10px] text-muted-foreground"
                >
                    <Info class="mt-0.5 h-3 w-3 shrink-0" />
                    <p>
                        This AI analysis is provided as a helpful guide only. It
                        is not legal advice. Consult a qualified attorney for
                        legal questions about this document.
                    </p>
                </div>
            </template>

            <div v-else class="py-8 text-center text-sm text-muted-foreground">
                Click "AI Legal Check" to analyze this document for potential
                legal concerns.
            </div>
        </div>

        <!-- Translation Tab -->
        <div
            v-if="activeTab === 'translate'"
            class="max-h-[calc(70vh-60px)] overflow-y-auto p-4"
        >
            <template v-if="translationResult">
                <p class="mb-2 text-xs font-medium text-muted-foreground">
                    Translated to English:
                </p>
                <div
                    class="rounded-lg bg-muted/50 p-4 text-sm leading-relaxed whitespace-pre-wrap"
                >
                    {{ translationResult.translated_text }}
                </div>
            </template>

            <div v-else class="py-8 text-center text-sm text-muted-foreground">
                Click "Translate to English" to translate this document.
            </div>
        </div>
    </div>

    <!-- Loading overlay for long operations -->
    <div
        v-if="analyzingLegal || translating"
        class="fixed inset-0 z-20 flex items-center justify-center bg-background/50 backdrop-blur-sm"
    >
        <div class="rounded-xl border bg-card p-6 shadow-xl">
            <div
                class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"
            />
            <p class="text-sm font-medium">
                {{
                    analyzingLegal
                        ? 'Analyzing document for legal risks...'
                        : 'Translating document...'
                }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                This may take a moment for large documents.
            </p>
        </div>
    </div>
</template>
