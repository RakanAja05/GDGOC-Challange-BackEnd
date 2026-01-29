<?php

namespace App\Models;

use App\Enums\AiAnalysisType;
use App\Services\AIAnalysisService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Message extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Conversation, Message>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, Message>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    protected static function booted(): void
    {
        static::created(function (self $message): void {
            if ($message->conversation_id === null) {
                return;
            }

            Cache::forget("ai:{$message->conversation_id}:".AiAnalysisType::Summary->value);

            $sender = $message->sender()->first();

            if (! $sender || $sender->role !== 'user') {
                return;
            }

            foreach ([AiAnalysisType::Issue, AiAnalysisType::Sentiment, AiAnalysisType::Priority] as $type) {
                Cache::forget("ai:{$message->conversation_id}:{$type->value}");
            }

            $conversation = $message->conversation()->first();

            if ($conversation === null) {
                return;
            }

            try {
                app(AIAnalysisService::class)->analyzeInbox($conversation);
            } catch (\Throwable $exception) {
                Log::warning('Inbox AI analysis failed.', [
                    'conversation_id' => $message->conversation_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
