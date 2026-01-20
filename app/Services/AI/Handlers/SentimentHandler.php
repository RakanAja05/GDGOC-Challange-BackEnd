<?php

namespace App\Services\AI\Handlers;

use App\Enums\AiAnalysisType;

class SentimentHandler extends AbstractGeminiHandler
{
    public function type(): AiAnalysisType
    {
        return AiAnalysisType::Sentiment;
    }

    protected function buildPrompt(string $conversationText): string
    {
        return <<<PROMPT
You are an assistant for customer support analytics.
Return ONLY valid JSON with fields:
{"label":"positive|neutral|negative","confidence":0.0-1.0}

Conversation:
{$conversationText}
PROMPT;
    }

    protected function normalizePayload(array $payload, ?string $rawText): array
    {
        $label = $payload['label'] ?? $payload['sentiment'] ?? null;
        $confidence = $payload['confidence'] ?? $payload['sentiment_score'] ?? null;

        return [
            'label' => is_string($label) ? strtolower(trim($label)) : null,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
        ];
    }

    protected function isValid(array $normalized): bool
    {
        $label = $normalized['label'] ?? null;
        $confidence = $normalized['confidence'] ?? null;

        if (! is_string($label) || ! in_array($label, ['positive', 'neutral', 'negative'], true)) {
            return false;
        }

        if (! is_float($confidence)) {
            return false;
        }

        return $confidence >= 0.0 && $confidence <= 1.0;
    }

    public function fallback(): array
    {
        return [
            'label' => 'neutral',
            'confidence' => 0.0,
        ];
    }
}
