<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;

class Conversation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'priority',
        'sentiment',
        'sentiment_score',
        'issue_category',
        'last_message_from',
        'last_message_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_message_at' => 'datetime',
        'sentiment_score' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<User, Conversation>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Message>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<AiInsight>
     */
    public function aiInsight(): HasOne
    {
        return $this->hasOne(AiInsight::class);
    }
}
