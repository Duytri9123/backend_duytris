<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversation extends Model
{
    protected $fillable = ['ai_provider_id', 'user_id', 'title', 'context', 'messages', 'total_tokens'];

    protected $casts = ['messages' => 'array', 'total_tokens' => 'integer'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
