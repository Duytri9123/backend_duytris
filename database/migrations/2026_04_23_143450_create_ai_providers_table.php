<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // OpenAI, Gemini, Claude...
            $table->string('provider');                // openai, google, anthropic, ollama
            $table->string('model');                   // gpt-4o, gemini-pro, claude-3...
            $table->text('api_key')->nullable();       // encrypted
            $table->string('base_url')->nullable();    // custom endpoint (Ollama)
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('max_tokens')->default(2048);
            $table->float('temperature')->default(0.7);
            $table->json('capabilities')->nullable();  // ['chat','image','code','search']
            $table->text('system_prompt')->nullable(); // custom system prompt
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
