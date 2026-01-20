<?php

namespace App\Services\AI\Handlers;

use App\Enums\AiAnalysisType;

class IssueClassificationHandler extends AbstractGeminiHandler
{
    public function type(): AiAnalysisType
    {
        return AiAnalysisType::Issue;
    }

    protected function buildPrompt(string $conversationText): string
    {
        return <<<PROMPT
Classify the customer's main issue.
Return ONLY valid JSON with fields:
{"category":"string","confidence":0.0-1.0}

Conversation:
{$conversationText}
PROMPT;
    }

    protected function normalizePayload(array $payload, ?string $rawText): array
    {
        $category = $payload['category'] ?? $payload['issue_category'] ?? null;
        $confidence = $payload['confidence'] ?? null;

        return [
            'category' => is_string($category) ? trim($category) : null,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
        ];
    }

    protected function isValid(array $normalized): bool
    {
        $category = $normalized['category'] ?? null;
        $confidence = $normalized['confidence'] ?? null;

        if (! is_string($category) || $category === '') {
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
            'category' => 'unknown',
            'confidence' => 0.0,
        ];
    }
}
