<?php

namespace App\Services\AI\Handlers;

use App\Enums\AiAnalysisType;

class PriorityHandler extends AbstractGeminiHandler
{
    public function type(): AiAnalysisType
    {
        return AiAnalysisType::Priority;
    }

    protected function buildPrompt(string $conversationText): string
    {
        return <<<PROMPT
Determine the urgency for customer support triage.
Return ONLY valid JSON with fields:
{"priority":"low|medium|high","confidence":0.0-1.0}

Conversation:
{$conversationText}
PROMPT;
    }

    protected function normalizePayload(array $payload, ?string $rawText): array
    {
        $priority = $payload['priority'] ?? null;
        $confidence = $payload['confidence'] ?? null;

        return [
            'priority' => is_string($priority) ? strtolower(trim($priority)) : null,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
        ];
    }

    protected function isValid(array $normalized): bool
    {
        $priority = $normalized['priority'] ?? null;
        $confidence = $normalized['confidence'] ?? null;

        if (! is_string($priority) || ! in_array($priority, ['low', 'medium', 'high'], true)) {
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
            'priority' => 'medium',
            'confidence' => 0.0,
        ];
    }
}
