<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'issue_category',
        'sentiment',
        'sentiment_score',
        'summary',
        'suggested_reply',
        'analyzed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'analyzed_at' => 'datetime',
        'sentiment_score' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Conversation, AiInsight>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
