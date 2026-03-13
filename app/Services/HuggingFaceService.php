<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HuggingFaceService
{
    private string $baseUrl = 'https://router.huggingface.co/hf-inference/models';

    public function __construct(
        private string $token,
    ) {}

    /**
     * Get embeddings via feature-extraction.
     *
     * @return array Token embeddings (nested arrays of floats)
     */
    public function featureExtraction(string $model, string|array $inputs): array
    {
        return $this->request($model, ['inputs' => $inputs, 'truncate' => true]);
    }

    /**
     * Translate text using a translation model.
     *
     * @return string Translated text
     */
    public function translate(string $model, string $inputs, ?string $srcLang = null, ?string $tgtLang = null): string
    {
        $payload = ['inputs' => $inputs];

        $params = [];
        if ($srcLang) {
            $params['src_lang'] = $srcLang;
        }
        if ($tgtLang) {
            $params['tgt_lang'] = $tgtLang;
        }
        if ($params) {
            $payload['parameters'] = $params;
        }

        $result = $this->request($model, $payload);

        // Response can be [{"translation_text": "..."}] or {"translation_text": "..."}
        if (isset($result[0]['translation_text'])) {
            return $result[0]['translation_text'];
        }
        if (isset($result['translation_text'])) {
            return $result['translation_text'];
        }

        return '';
    }

    /**
     * Zero-shot classification.
     *
     * @return array{labels: string[], scores: float[]}
     */
    public function zeroShotClassification(string $model, string $inputs, array $candidateLabels, bool $multiLabel = true): array
    {
        return $this->request($model, [
            'inputs' => $inputs,
            'parameters' => [
                'candidate_labels' => $candidateLabels,
                'multi_label' => $multiLabel,
            ],
        ]);
    }

    private function request(string $model, array $payload): array
    {
        $response = Http::withToken($this->token)
            ->timeout(120)
            ->retry(2, 10000, function ($exception) {
                // Retry on 503 (model loading)
                return $exception instanceof \Illuminate\Http\Client\RequestException
                    && $exception->response->status() === 503;
            })
            ->post("{$this->baseUrl}/{$model}", $payload);

        $response->throw();

        return $response->json();
    }
}
