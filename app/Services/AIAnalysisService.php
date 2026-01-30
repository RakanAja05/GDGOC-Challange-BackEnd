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
     * Auto inbox intelligence: issue classification, sentiment analysis, and priority.
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function analyzeInbox(Conversation $conversation): array
    {
        $conversationText = $this->buildConversationText($conversation);

        $issue = $this->analyzeType($conversation, AiAnalysisType::Issue, $conversationText, cache: true, persist: false);
        $sentiment = $this->analyzeType($conversation, AiAnalysisType::Sentiment, $conversationText, cache: true, persist: false);
        $priority = $this->analyzeType($conversation, AiAnalysisType::Priority, $conversationText, cache: true, persist: false);

        $priorityData = $priority['data'];

        if (($sentiment['data']['label'] ?? null) === 'negative') {
            $priorityData = array_merge($priorityData, ['priority' => 'high']);
            Cache::put($this->cacheKey($conversation->id, AiAnalysisType::Priority), $priorityData, self::CACHE_TTL_SECONDS);
        }

        $this->persistInboxResults($conversation, $issue['data'], $sentiment['data'], $priorityData);

        return [
            'data' => [
                'issue_category' => $issue['data']['category'] ?? null,
                'sentiment' => $sentiment['data']['label'] ?? null,
                'sentiment_score' => $sentiment['data']['confidence'] ?? null,
                'priority' => $priorityData['priority'] ?? null,
            ],
            'meta' => [
                'issue' => ['cached' => $issue['cached'], 'fallback' => $issue['fallback']],
                'sentiment' => ['cached' => $sentiment['cached'], 'fallback' => $sentiment['fallback']],
                'priority' => ['cached' => $priority['cached'], 'fallback' => $priority['fallback']],
            ],
        ];
    }

    /**
     * Conversation summary (cached, invalidated on new message).
     *
     * @return array{data: array<string, mixed>, cached: bool, fallback: bool}
     */
    public function getSummary(Conversation $conversation): array
    {
        return $this->analyzeType($conversation, AiAnalysisType::Summary, $this->buildConversationText($conversation), cache: true, persist: true);
    }

    /**
     * Suggested reply (no cache, no persistence).
     *
     * @return array{data: array<string, mixed>, cached: bool, fallback: bool}
     */
    public function suggestReply(Conversation $conversation): array
    {
        return $this->analyzeType($conversation, AiAnalysisType::Reply, $this->buildConversationText($conversation), cache: false, persist: false);
    }

    private function buildConversationText(Conversation $conversation): string
    {
        $messages = $conversation->messages()
            ->latest('created_at')
            ->limit(20)
            ->get(['sender_type', 'content'])
            ->reverse()
            ->values();

        return $messages->map(function ($message) {
            return strtoupper((string) $message->sender_type).': '.$message->content;
        })->implode("\n");
    }

    /**
     * @param array<string, mixed> $data
     */
    private function analyzeType(
        Conversation $conversation,
        AiAnalysisType $type,
        string $conversationText,
        bool $cache,
        bool $persist
    ): array {
        $cacheKey = $this->cacheKey($conversation->id, $type);

        if ($cache && Cache::has($cacheKey)) {
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

        $startedAt = microtime(true);

        try {
            $data = $handler->handle($conversationText);

            if ($persist) {
                $this->persistResult($conversation, $type, $data);
            }

            if ($cache) {
                Cache::put($cacheKey, $data, self::CACHE_TTL_SECONDS);
            }

            Log::info('AI handler succeeded.', [
                'type' => $type->value,
                'conversation_id' => $conversation->id,
                'execution_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

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
                'execution_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return [
                'data' => $handler->fallback(),
                'cached' => false,
                'fallback' => true,
            ];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistResult(Conversation $conversation, AiAnalysisType $type, array $data): void
    {
        if ($type === AiAnalysisType::Summary) {
            AiInsight::updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'summary' => $data['summary'] ?? null,
                    'analyzed_at' => now(),
                ]
            );

            return;
        }

        if ($type === AiAnalysisType::Priority) {
            $conversation->forceFill([
                'priority' => $data['priority'] ?? 'medium',
            ])->save();

            return;
        }

        if ($type === AiAnalysisType::Sentiment) {
            $conversation->forceFill([
                'sentiment' => $data['label'] ?? null,
                'sentiment_score' => $data['confidence'] ?? null,
            ])->save();

            return;
        }

        if ($type === AiAnalysisType::Issue) {
            $conversation->forceFill([
                'issue_category' => $data['category'] ?? null,
            ])->save();
        }
    }

    /**
     * @param array<string, mixed> $issue
     * @param array<string, mixed> $sentiment
     * @param array<string, mixed> $priority
     */
    private function persistInboxResults(
        Conversation $conversation,
        array $issue,
        array $sentiment,
        array $priority
    ): void {
        $conversation->forceFill([
            'issue_category' => $issue['category'] ?? null,
            'sentiment' => $sentiment['label'] ?? null,
            'sentiment_score' => $sentiment['confidence'] ?? null,
            'priority' => $priority['priority'] ?? $conversation->priority,
        ])->save();
    }

    public function cacheKey(int $conversationId, AiAnalysisType $type): string
    {
        return "ai:{$conversationId}:{$type->value}";
    }

    public function invalidateConversationCache(int $conversationId): void
    {
        foreach (AiAnalysisType::cases() as $type) {
            Cache::forget($this->cacheKey($conversationId, $type));
        }
    }
}
