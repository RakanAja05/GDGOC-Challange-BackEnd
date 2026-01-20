<?php

namespace App\Services\AI\Handlers;

use App\Enums\AiAnalysisType;

interface AiAnalysisHandler
{
    public function type(): AiAnalysisType;

    /**
     * @return array<string, mixed>
     */
    public function handle(string $conversationText): array;

    /**
     * @return array<string, mixed>
     */
    public function fallback(): array;
}
