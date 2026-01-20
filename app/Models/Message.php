<?php

namespace App\Models;

use App\Enums\AiAnalysisType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Message extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_type',
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
     * Agent sender (nullable when sender_type is customer).
     *
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

            foreach (AiAnalysisType::cases() as $type) {
                Cache::forget("ai:{$message->conversation_id}:{$type->value}");
            }
        });
    }
}
