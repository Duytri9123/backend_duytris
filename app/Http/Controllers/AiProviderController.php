<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AiProviderController extends Controller
{
    // Danh sách providers
    public function index(): JsonResponse
    {
        $providers = AiProvider::select(['id','name','provider','model','base_url','is_active','is_default','max_tokens','temperature','capabilities','system_prompt','created_at'])
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->get();

        return response()->json(['data' => $providers]);
    }

    // Tạo provider mới
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'provider'      => 'required|in:openai,google,anthropic,ollama,custom',
            'model'         => 'required|string|max:100',
            'api_key'       => 'nullable|string',
            'base_url'      => 'nullable|url',
            'is_active'     => 'boolean',
            'is_default'    => 'boolean',
            'max_tokens'    => 'integer|min:1|max:128000',
            'temperature'   => 'numeric|min:0|max:2',
            'capabilities'  => 'nullable|array',
            'system_prompt' => 'nullable|string',
        ]);

        if (!empty($data['is_default'])) {
            AiProvider::where('is_default', true)->update(['is_default' => false]);
        }

        $provider = AiProvider::create($data);

        return response()->json(['data' => $provider, 'message' => 'Tạo thành công'], 201);
    }

    // Cập nhật provider
    public function update(Request $request, AiProvider $aiProvider): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'string|max:100',
            'provider'      => 'in:openai,google,anthropic,ollama,custom',
            'model'         => 'string|max:100',
            'api_key'       => 'nullable|string',
            'base_url'      => 'nullable|url',
            'is_active'     => 'boolean',
            'is_default'    => 'boolean',
            'max_tokens'    => 'integer|min:1|max:128000',
            'temperature'   => 'numeric|min:0|max:2',
            'capabilities'  => 'nullable|array',
            'system_prompt' => 'nullable|string',
        ]);

        if (!empty($data['is_default'])) {
            AiProvider::where('id', '!=', $aiProvider->id)->update(['is_default' => false]);
        }

        // Không update api_key nếu không gửi lên (giữ nguyên)
        if (!isset($data['api_key'])) {
            unset($data['api_key']);
        }

        $aiProvider->update($data);

        return response()->json(['data' => $aiProvider, 'message' => 'Cập nhật thành công']);
    }

    // Xóa provider
    public function destroy(AiProvider $aiProvider): JsonResponse
    {
        $aiProvider->delete();
        return response()->json(['message' => 'Đã xóa']);
    }

    // Test kết nối
    public function test(AiProvider $aiProvider): JsonResponse
    {
        try {
            $result = $this->callProvider($aiProvider, [
                ['role' => 'user', 'content' => 'Say "OK" in one word.']
            ]);
            return response()->json(['success' => true, 'response' => $result['content'], 'tokens' => $result['tokens']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // Chat với AI
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id'     => 'required|exists:ai_providers,id',
            'messages'        => 'required|array',
            'messages.*.role'    => 'required|in:user,assistant,system',
            'messages.*.content' => 'required|string',
            'conversation_id' => 'nullable|exists:ai_conversations,id',
            'context'         => 'nullable|string',
        ]);

        $provider = AiProvider::findOrFail($data['provider_id']);

        if (!$provider->is_active) {
            return response()->json(['error' => 'Provider không hoạt động'], 422);
        }

        try {
            $result = $this->callProvider($provider, $data['messages']);

            // Lưu conversation
            $messages = $data['messages'];
            $messages[] = ['role' => 'assistant', 'content' => $result['content'], 'created_at' => now()->toISOString()];

            if (!empty($data['conversation_id'])) {
                $conv = AiConversation::find($data['conversation_id']);
                if ($conv) {
                    $existing = $conv->messages ?? [];
                    $conv->update([
                        'messages'     => array_merge($existing, array_slice($messages, -2)),
                        'total_tokens' => $conv->total_tokens + ($result['tokens'] ?? 0),
                    ]);
                }
            } else {
                $conv = AiConversation::create([
                    'ai_provider_id' => $provider->id,
                    'user_id'        => Auth::id(),
                    'context'        => $data['context'] ?? 'general',
                    'title'          => substr($data['messages'][0]['content'], 0, 60),
                    'messages'       => $messages,
                    'total_tokens'   => $result['tokens'] ?? 0,
                ]);
            }

            return response()->json([
                'content'         => $result['content'],
                'tokens'          => $result['tokens'],
                'conversation_id' => $conv->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // Lấy danh sách conversations
    public function conversations(Request $request): JsonResponse
    {
        $convs = AiConversation::with('provider:id,name,provider')
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json($convs);
    }

    // ─── Private: gọi API theo provider ─────────────────────────────────────
    private function callProvider(AiProvider $provider, array $messages): array
    {
        $apiKey = $provider->getDecryptedApiKey();

        // Thêm system prompt nếu có
        if ($provider->system_prompt) {
            array_unshift($messages, ['role' => 'system', 'content' => $provider->system_prompt]);
        }

        return match($provider->provider) {
            'openai'    => $this->callOpenAI($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'google'    => $this->callGemini($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'anthropic' => $this->callClaude($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'ollama'    => $this->callOllama($provider->base_url, $provider->model, $messages, $provider->max_tokens),
            default     => $this->callCustom($provider->base_url, $apiKey, $provider->model, $messages, $provider->max_tokens),
        };
    }

    private function callOpenAI(string $apiKey, string $model, array $messages, int $maxTokens, float $temp): array
    {
        $res = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => $temp,
            ]);

        if (!$res->successful()) throw new \Exception('OpenAI error: ' . $res->body());

        $json = $res->json();
        return [
            'content' => $json['choices'][0]['message']['content'],
            'tokens'  => $json['usage']['total_tokens'] ?? 0,
        ];
    }

    private function callGemini(string $apiKey, string $model, array $messages, int $maxTokens, float $temp): array
    {
        // Convert messages format
        $contents = array_map(fn($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], array_filter($messages, fn($m) => $m['role'] !== 'system'));

        $res = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents'         => array_values($contents),
                'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => $temp],
            ]);

        if (!$res->successful()) throw new \Exception('Gemini error: ' . $res->body());

        $json = $res->json();
        return [
            'content' => $json['candidates'][0]['content']['parts'][0]['text'],
            'tokens'  => $json['usageMetadata']['totalTokenCount'] ?? 0,
        ];
    }

    private function callClaude(string $apiKey, string $model, array $messages, int $maxTokens, float $temp): array
    {
        $system = '';
        $msgs   = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') $system = $m['content'];
            else $msgs[] = $m;
        }

        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'temperature' => $temp, 'messages' => $msgs];
        if ($system) $body['system'] = $system;

        $res = Http::withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', $body);

        if (!$res->successful()) throw new \Exception('Claude error: ' . $res->body());

        $json = $res->json();
        return [
            'content' => $json['content'][0]['text'],
            'tokens'  => ($json['usage']['input_tokens'] ?? 0) + ($json['usage']['output_tokens'] ?? 0),
        ];
    }

    private function callOllama(string $baseUrl, string $model, array $messages, int $maxTokens): array
    {
        $url = rtrim($baseUrl ?: 'http://localhost:11434', '/') . '/api/chat';
        $res = Http::timeout(120)->post($url, [
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
            'options'  => ['num_predict' => $maxTokens],
        ]);

        if (!$res->successful()) throw new \Exception('Ollama error: ' . $res->body());

        $json = $res->json();
        return [
            'content' => $json['message']['content'],
            'tokens'  => ($json['prompt_eval_count'] ?? 0) + ($json['eval_count'] ?? 0),
        ];
    }

    private function callCustom(string $baseUrl, string $apiKey, string $model, array $messages, int $maxTokens): array
    {
        $res = Http::withToken($apiKey)->timeout(60)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model'      => $model,
                'messages'   => $messages,
                'max_tokens' => $maxTokens,
            ]);

        if (!$res->successful()) throw new \Exception('Custom API error: ' . $res->body());

        $json = $res->json();
        return [
            'content' => $json['choices'][0]['message']['content'],
            'tokens'  => $json['usage']['total_tokens'] ?? 0,
        ];
    }
}
