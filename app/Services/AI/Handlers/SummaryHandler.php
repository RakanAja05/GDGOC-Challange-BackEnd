<?php

namespace App\Services\AI\Handlers;

use App\Enums\AiAnalysisType;

class SummaryHandler extends AbstractGeminiHandler
{
    public function type(): AiAnalysisType
    {
        return AiAnalysisType::Summary;
    }

    protected function buildPrompt(string $conversationText): string
    {
        return <<<PROMPT
Summarize the conversation for customer support.
Return ONLY valid JSON with fields:
{"summary":"string"}

Conversation:
{$conversationText}
PROMPT;
    }

    protected function normalizePayload(array $payload, ?string $rawText): array
    {
        $summary = $payload['summary'] ?? null;

        return [
            'summary' => is_string($summary) ? trim($summary) : null,
        ];
    }

    protected function isValid(array $normalized): bool
    {
        $summary = $normalized['summary'] ?? null;

        return is_string($summary) && $summary !== '';
    }

    public function fallback(): array
    {
        return [
            'summary' => '',
        ];
    }
}
