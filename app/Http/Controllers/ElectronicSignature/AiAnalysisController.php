<?php

namespace App\Http\Controllers\ElectronicSignature;

use App\Http\Controllers\Controller;
use App\Models\DocumentRecipient;
use App\Services\HuggingFaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAnalysisController extends Controller
{
    private const CLASSIFICATION_MODEL = 'facebook/bart-large-mnli';
    private const TRANSLATION_MODEL = 'Helsinki-NLP/opus-mt-mul-en';

    private const CANDIDATE_LABELS = [
        'liability',
        'indemnification',
        'non-compete',
        'arbitration',
        'termination',
        'confidentiality',
        'penalty',
        'intellectual property assignment',
        'warranty disclaimer',
        'limitation of liability',
    ];

    private const CATEGORY_LABELS = [
        'liability' => 'Liability Clause',
        'indemnification' => 'Indemnification',
        'non-compete' => 'Non-Compete / Restrictive Covenant',
        'arbitration' => 'Mandatory Arbitration',
        'termination' => 'Termination Rights',
        'confidentiality' => 'Confidentiality / NDA',
        'penalty' => 'Penalties / Liquidated Damages',
        'intellectual property assignment' => 'IP Assignment',
        'warranty disclaimer' => 'Warranty Disclaimer',
        'limitation of liability' => 'Limitation of Liability',
    ];

    private const CATEGORY_DESCRIPTIONS = [
        'liability' => 'This clause may impose broad liability obligations on you.',
        'indemnification' => 'You may be required to cover the other party\'s losses or legal costs.',
        'non-compete' => 'This could restrict your ability to work in the same field or industry.',
        'arbitration' => 'You may be waiving your right to a court trial or class action.',
        'termination' => 'The agreement could be ended suddenly without protection for you.',
        'confidentiality' => 'You may be bound to strict secrecy obligations regarding shared information.',
        'penalty' => 'You could face significant financial penalties for breaches.',
        'intellectual property assignment' => 'You may be giving up ownership of intellectual property you create.',
        'warranty disclaimer' => 'The other party may not guarantee the quality or fitness of what they provide.',
        'limitation of liability' => 'The other party\'s financial responsibility to you may be capped.',
    ];

    public function analyzeLegal(Request $request, DocumentRecipient $recipient): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:50000']);
        $text = $request->input('text');

        $service = $this->getService();

        // Check if non-English and auto-translate
        $isEnglish = $this->isLikelyEnglish($text);
        $translatedText = null;

        if (! $isEnglish) {
            $translatedText = $this->translateText($service, $text);
            $textToAnalyze = $translatedText;
        } else {
            $textToAnalyze = $text;
        }

        // Split into meaningful paragraphs
        $paragraphs = $this->splitIntoParagraphs($textToAnalyze);

        if (empty($paragraphs)) {
            return response()->json([
                'findings' => [],
                'translated' => ! $isEnglish,
                'translated_text' => $translatedText,
                'paragraphs_analyzed' => 0,
            ]);
        }

        // Classify each paragraph against legal risk categories
        $findings = [];

        foreach ($paragraphs as $paragraph) {
            $result = $service->zeroShotClassification(
                self::CLASSIFICATION_MODEL,
                $paragraph,
                self::CANDIDATE_LABELS,
                true,
            );

            $labels = $result['labels'] ?? [];
            $scores = $result['scores'] ?? [];

            // Flag paragraphs where the top label scores above threshold
            if (! empty($labels) && ! empty($scores) && $scores[0] > 0.50) {
                $topLabel = $labels[0];
                $topScore = $scores[0];

                $findings[] = [
                    'text' => $paragraph,
                    'category' => $topLabel,
                    'category_label' => self::CATEGORY_LABELS[$topLabel] ?? ucwords($topLabel),
                    'description' => self::CATEGORY_DESCRIPTIONS[$topLabel] ?? '',
                    'score' => round($topScore, 3),
                    'level' => $topScore > 0.80 ? 'high' : ($topScore > 0.60 ? 'medium' : 'low'),
                ];
            }
        }

        // Sort findings by score descending
        usort($findings, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'findings' => $findings,
            'translated' => ! $isEnglish,
            'translated_text' => $translatedText,
            'paragraphs_analyzed' => count($paragraphs),
        ]);
    }

    public function translate(Request $request, DocumentRecipient $recipient): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:50000']);
        $text = $request->input('text');

        $service = $this->getService();
        $translated = $this->translateText($service, $text);

        return response()->json([
            'translated_text' => $translated,
            'original_text' => $text,
        ]);
    }

    private function translateText(HuggingFaceService $service, string $text): string
    {
        $chunks = $this->splitIntoChunks($text, 100);
        $translated = [];

        foreach ($chunks as $chunk) {
            $translated[] = $service->translate(self::TRANSLATION_MODEL, $chunk);
        }

        return implode("\n\n", $translated);
    }

    private function splitIntoParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/', $text);
        $result = [];

        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (strlen($p) > 30) {
                $result[] = $p;
            }
        }

        return array_slice($result, 0, 30);
    }

    private function splitIntoChunks(string $text, int $maxWords): array
    {
        $words = explode(' ', $text);
        $chunks = [];
        $current = [];

        foreach ($words as $word) {
            $current[] = $word;
            if (count($current) >= $maxWords) {
                $chunks[] = implode(' ', $current);
                $current = [];
            }
        }

        if (! empty($current)) {
            $chunks[] = implode(' ', $current);
        }

        return $chunks;
    }

    private function isLikelyEnglish(string $text): bool
    {
        $sample = substr($text, 0, 2000);
        $asciiLetters = preg_match_all('/[a-zA-Z]/', $sample);
        $allLetters = preg_match_all('/\pL/u', $sample);

        if ($allLetters === 0) {
            return true;
        }

        $stopWords = ['the', 'and', 'for', 'that', 'this', 'with', 'are', 'from', 'have', 'will', 'shall', 'not'];
        $lowerSample = strtolower($sample);
        $stopWordCount = 0;

        foreach ($stopWords as $word) {
            $stopWordCount += substr_count($lowerSample, " $word ");
        }

        return ($asciiLetters / $allLetters) > 0.85 && $stopWordCount > 3;
    }

    private function getService(): HuggingFaceService
    {
        $token = config('services.huggingface.token');
        abort_unless($token, 500, 'Hugging Face API token is not configured.');

        return new HuggingFaceService($token);
    }
}
