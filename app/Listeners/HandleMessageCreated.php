<?php

namespace App\Listeners;

use App\Events\MessageCreated;
use App\Services\AIAnalysisService;
use Illuminate\Support\Facades\Log;

class HandleMessageCreated
{
    public function __construct(
        private readonly AIAnalysisService $analysisService,
    ) {
    }

    public function handle(MessageCreated $event): void
    {
        $message = $event->message;

        if ($message->conversation_id === null) {
            return;
        }

        $this->analysisService->invalidateConversationCache($message->conversation_id);

        if ($message->sender_type !== 'user') {
            return;
        }

        $conversation = $message->conversation()->first();

        if ($conversation === null) {
            return;
        }

        try {
            $this->analysisService->analyzeInbox($conversation);
        } catch (\Throwable $exception) {
            Log::warning('Inbox AI analysis failed.', [
                'conversation_id' => $message->conversation_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
