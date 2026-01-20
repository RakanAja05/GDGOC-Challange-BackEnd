<?php

namespace App\Services\AI\Handlers;

use App\Enums\AiAnalysisType;

class SuggestedReplyHandler extends AbstractGeminiHandler
{
    public function type(): AiAnalysisType
    {
        return AiAnalysisType::Reply;
    }

    protected function buildPrompt(string $conversationText): string
    {
        return <<<PROMPT
Draft a helpful agent reply for the customer.
Return ONLY valid JSON with fields:
{"reply":"string"}

Conversation:
{$conversationText}
PROMPT;
    }

    protected function normalizePayload(array $payload, ?string $rawText): array
    {
        $reply = $payload['reply'] ?? $payload['suggested_reply'] ?? null;

        return [
            'reply' => is_string($reply) ? trim($reply) : null,
        ];
    }

    protected function isValid(array $normalized): bool
    {
        $reply = $normalized['reply'] ?? null;

        return is_string($reply) && $reply !== '';
    }

    public function fallback(): array
    {
        return [
            'reply' => '',
        ];
    }
}
