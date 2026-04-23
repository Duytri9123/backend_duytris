<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class AiProvider extends Model
{
    protected $fillable = [
        'name', 'provider', 'model', 'api_key', 'base_url',
        'is_active', 'is_default', 'max_tokens', 'temperature',
        'capabilities', 'system_prompt',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_default'   => 'boolean',
        'capabilities' => 'array',
        'temperature'  => 'float',
        'max_tokens'   => 'integer',
    ];

    // Ẩn api_key khi serialize
    protected $hidden = ['api_key'];

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    // Decrypt api_key khi cần dùng
    public function getDecryptedApiKey(): ?string
    {
        if (!$this->api_key) return null;
        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Exception) {
            return $this->api_key; // fallback nếu chưa encrypt
        }
    }

    // Encrypt khi set
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }
}
