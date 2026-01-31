<?php

namespace App\Services\AI\Handlers;

use App\Services\GeminiService;
use InvalidArgumentException;

abstract class AbstractGeminiHandler implements AiAnalysisHandler
{
    public function __construct(protected GeminiService $gemini)
    {
    }

    public function handle(string $conversationText): array
    {
        $prompt = $this->buildPrompt($conversationText);
        $rawText = $this->gemini->getText($prompt);
        $payload = $this->parseJson($rawText);

        $normalized = $this->normalizePayload($payload, $rawText);

        if (! $this->isValid($normalized)) {
            throw new InvalidArgumentException('Invalid AI response.');
        }

        return $normalized;
    }

    abstract protected function buildPrompt(string $conversationText): string;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    abstract protected function normalizePayload(array $payload, ?string $rawText): array;

    /**
     * @param array<string, mixed> $normalized
     */
    protected function isValid(array $normalized): bool
    {
        return ! empty($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseJson(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $clean = trim($text);

        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(json)?\s*/i', '', $clean) ?? $clean;
            $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        }

        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            $firstBrace = strpos($clean, '{');
            $lastBrace = strrpos($clean, '}');

            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $candidate = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
                $decoded = json_decode($candidate, true);
            }
        }

        return is_array($decoded) ? $decoded : [];
    }
}
