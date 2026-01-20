<?php

namespace App\Services;

use App\Enums\AiAnalysisType;
use App\Models\AiInsight;
use App\Models\Conversation;
use App\Services\AI\Handlers\AiAnalysisHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIAnalysisService
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @var array<string, AiAnalysisHandler>
     */
    private array $handlersByType;

    /**
     * @param iterable<int, AiAnalysisHandler> $handlers
     */
    public function __construct(iterable $handlers)
    {
        $this->handlersByType = [];

        foreach ($handlers as $handler) {
            $this->handlersByType[$handler->type()->value] = $handler;
        }
    }

    /**
     * Centralized entry point: caches successful responses per conversation+type and
     * gracefully falls back when AI output is invalid or times out.
     *
     * @return array{data: array<string, mixed>, cached: bool, fallback: bool}
     */
    public function analyze(Conversation $conversation, AiAnalysisType $type): array
    {
        $cacheKey = $this->cacheKey($conversation->id, $type);

        if (Cache::has($cacheKey)) {
            return [
                'data' => Cache::get($cacheKey, []),
                'cached' => true,
                'fallback' => false,
            ];
        }

        $handler = $this->handlersByType[$type->value] ?? null;

        if ($handler === null) {
            Log::warning('AI handler missing for type.', [
                'type' => $type->value,
                'conversation_id' => $conversation->id,
            ]);

            return [
                'data' => [],
                'cached' => false,
                'fallback' => true,
            ];
        }

        $conversationText = $this->buildConversationText($conversation);

        try {
            $data = $handler->handle($conversationText);
            $this->persistResult($conversation, $type, $data);
            Cache::put($cacheKey, $data, self::CACHE_TTL_SECONDS);

            return [
                'data' => $data,
                'cached' => false,
                'fallback' => false,
            ];
        } catch (Throwable $exception) {
            Log::warning('AI handler failed. Returning fallback.', [
                'type' => $type->value,
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'data' => $handler->fallback(),
                'cached' => false,
                'fallback' => true,
            ];
        }
    }

    private function buildConversationText(Conversation $conversation): string
    {
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get(['sender_type', 'content']);

        return $messages->map(function ($message) {
            return strtoupper((string) $message->sender_type).': '.$message->content;
        })->implode("\n");
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistResult(Conversation $conversation, AiAnalysisType $type, array $data): void
    {
        $payload = ['analyzed_at' => now()];

        if ($type === AiAnalysisType::Sentiment) {
            $payload['sentiment'] = $data['label'] ?? null;
            $payload['sentiment_score'] = $data['confidence'] ?? null;
        }

        if ($type === AiAnalysisType::Summary) {
            $payload['summary'] = $data['summary'] ?? null;
        }

        if ($type === AiAnalysisType::Issue) {
            $payload['issue_category'] = $data['category'] ?? null;
        }

        if ($type === AiAnalysisType::Reply) {
            $payload['suggested_reply'] = $data['reply'] ?? null;
        }

        if (! empty($payload)) {
            AiInsight::updateOrCreate(
                ['conversation_id' => $conversation->id],
                $payload
            );
        }

        if ($type === AiAnalysisType::Priority) {
            $conversation->forceFill([
                'priority' => $data['priority'] ?? 'medium',
            ])->save();
        }
    }

    public function cacheKey(int $conversationId, AiAnalysisType $type): string
    {
        return "ai:{$conversationId}:{$type->value}";
    }
}
