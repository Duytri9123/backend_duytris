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
            'provider'      => 'required|in:openai,openrouter,google,anthropic,ollama,custom',
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
            'provider'      => 'in:openai,openrouter,google,anthropic,ollama,custom',
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

    // Chat với AI — tự động chọn model tối ưu theo context
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id'        => 'nullable|exists:ai_providers,id',
            'messages'           => 'required|array|min:1',
            'messages.*.role'    => 'required|in:user,assistant,system',
            'messages.*.content' => 'required',
            'conversation_id'    => 'nullable|integer',
            'context'            => 'nullable|string',
            'has_image'          => 'nullable|boolean',
        ]);

        // ── Smart model selection ──────────────────────────────────────────
        if (!empty($data['provider_id'])) {
            $provider = AiProvider::find($data['provider_id']);
        } else {
            // Nếu có ảnh → dùng vision model
            $hasImage = !empty($data['has_image']) || $this->messagesHaveImage($data['messages']);
            if ($hasImage) {
                $provider = $this->selectVisionProvider();
            } else {
                $provider = $this->selectOptimalProvider($data['context'] ?? 'general', $data['messages']);
            }
        }
        // Fallback nếu không tìm được provider
        if (!$provider || !$provider->is_active) {
            $provider = AiProvider::where('is_default', true)->where('is_active', true)->first()
                ?? AiProvider::where('is_active', true)->first();
        }

        if (!$provider) {
            return response()->json(['error' => 'Không có AI provider nào hoạt động. Vui lòng cấu hình trong phần AI Management.'], 422);
        }

        try {
            $result = $this->callProvider($provider, $data['messages']);

            // Lưu conversation (bỏ qua nếu lỗi — không ảnh hưởng response)
            $convId = null;
            try {
                $allMessages = $data['messages'];
                $allMessages[] = [
                    'role'       => 'assistant',
                    'content'    => $result['content'],
                    'created_at' => now()->toISOString(),
                ];

                if (!empty($data['conversation_id'])) {
                    $conv = AiConversation::find($data['conversation_id']);
                    if ($conv) {
                        $existing = is_array($conv->messages) ? $conv->messages : [];
                        $conv->update([
                            'messages'     => array_merge($existing, array_slice($allMessages, -2)),
                            'total_tokens' => ($conv->total_tokens ?? 0) + ($result['tokens'] ?? 0),
                            'updated_at'   => now(),
                        ]);
                        $convId = $conv->id;
                    }
                } else {
                    $conv = AiConversation::create([
                        'ai_provider_id' => $provider->id,
                        'user_id'        => Auth::id() ?? null,
                        'context'        => $data['context'] ?? 'admin_assistant',
                        'title'          => mb_substr($data['messages'][0]['content'] ?? 'Cuộc hội thoại', 0, 60),
                        'messages'       => $allMessages,
                        'total_tokens'   => $result['tokens'] ?? 0,
                    ]);
                    $convId = $conv->id ?? null;
                }
            } catch (\Exception $convErr) {
                // Lỗi lưu conversation không ảnh hưởng response AI
                \Log::warning('AI conversation save failed: ' . $convErr->getMessage());
            }

            return response()->json([
                'content'         => $result['content'],
                'tokens'          => $result['tokens'] ?? 0,
                'conversation_id' => $convId,
                'model_used'      => $provider->model,
            ]);

        } catch (\Exception $e) {
            // Nếu lỗi quota/rate limit/not found, thử fallback sang model khác
            $errMsg = $e->getMessage();
            if (str_contains($errMsg, '429') || str_contains($errMsg, 'quota')
                || str_contains($errMsg, '404') || str_contains($errMsg, 'NOT_FOUND')
                || str_contains($errMsg, 'not found')) {
                return $this->chatWithFallback($data, $provider);
            }
            return response()->json(['error' => $errMsg], 422);
        }
    }

    /**
     * AI Chat cho khách hàng frontend — public endpoint (không cần đăng nhập)
     * Rate limited ở route level (30 req/phút)
     */
    public function customerChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages'        => 'required|array|min:1|max:20',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
            'has_image'       => 'nullable|boolean',
        ]);

        // Chọn provider phù hợp
        $hasImage = !empty($data['has_image']) || $this->messagesHaveImage($data['messages']);
        if ($hasImage) {
            $provider = $this->selectVisionProvider();
        } else {
            $provider = $this->selectOptimalProvider('customer_chat', $data['messages']);
        }

        // Fallback nếu không tìm được provider
        if (!$provider || !$provider->is_active) {
            $provider = AiProvider::where('is_default', true)->where('is_active', true)->first()
                ?? AiProvider::where('is_active', true)->first();
        }

        if (!$provider) {
            return response()->json(['error' => 'Dịch vụ AI tạm thời không khả dụng.'], 503);
        }

        try {
            $result = $this->callProvider($provider, $data['messages']);

            // Lưu conversation (không gắn user_id — khách vãng lai)
            $convId = null;
            try {
                $allMessages = $data['messages'];
                $allMessages[] = [
                    'role'       => 'assistant',
                    'content'    => $result['content'],
                    'created_at' => now()->toISOString(),
                ];

                if (!empty($data['conversation_id'])) {
                    $conv = AiConversation::find($data['conversation_id']);
                    if ($conv && $conv->context === 'customer_chat') {
                        $existing = is_array($conv->messages) ? $conv->messages : [];
                        $conv->update([
                            'messages'     => array_merge($existing, array_slice($allMessages, -2)),
                            'total_tokens' => ($conv->total_tokens ?? 0) + ($result['tokens'] ?? 0),
                            'updated_at'   => now(),
                        ]);
                        $convId = $conv->id;
                    }
                } else {
                    $conv = AiConversation::create([
                        'ai_provider_id' => $provider->id,
                        'user_id'        => null,
                        'context'        => 'customer_chat',
                        'title'          => mb_substr($data['messages'][0]['content'] ?? 'Khách hàng', 0, 60),
                        'messages'       => $allMessages,
                        'total_tokens'   => $result['tokens'] ?? 0,
                    ]);
                    $convId = $conv->id ?? null;
                }
            } catch (\Exception $convErr) {
                \Log::warning('Customer AI conversation save failed: ' . $convErr->getMessage());
            }

            return response()->json([
                'content'         => $result['content'],
                'tokens'          => $result['tokens'] ?? 0,
                'conversation_id' => $convId,
                'model_used'      => $provider->model,
            ]);

        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            if (str_contains($errMsg, '429') || str_contains($errMsg, 'quota')
                || str_contains($errMsg, '404') || str_contains($errMsg, 'NOT_FOUND')
                || str_contains($errMsg, 'not found')) {
                return $this->chatWithFallback($data, $provider);
            }
            \Log::error('Customer AI chat error: ' . $errMsg);
            return response()->json(['error' => 'Dịch vụ AI tạm thời không khả dụng. Vui lòng thử lại sau.'], 503);
        }
    }

    /**
     * Kiểm tra messages có chứa ảnh không
     */
    private function messagesHaveImage(array $messages): bool
    {
        foreach ($messages as $m) {
            if (is_array($m['content'])) {
                foreach ($m['content'] as $part) {
                    if (isset($part['type']) && $part['type'] === 'image_url') return true;
                }
            }
        }
        return false;
    }

    /**
     * Chọn vision model — ưu tiên google/gemma-3-4b-it:free (đã test OK)
     */
    private function selectVisionProvider(): ?AiProvider
    {
        // Thử theo thứ tự ưu tiên
        $visionModels = [
            'google/gemma-3-4b-it:free',
            'google/gemma-4-31b-it:free',
            'google/gemma-4-26b-a4b-it:free',
            'nvidia/nemotron-nano-12b-v2-vl:free',
            'baidu/qianfan-ocr-fast:free',
        ];

        foreach ($visionModels as $model) {
            $p = AiProvider::where('is_active', true)->where('model', $model)->first();
            if ($p) return $p;
        }

        // Fallback: provider có capability 'image'
        return AiProvider::where('is_active', true)
            ->whereJsonContains('capabilities', 'image')
            ->first()
            ?? AiProvider::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Chọn model tối ưu theo context và độ phức tạp
     * Ưu tiên: OpenRouter (nhiều model mạnh) → Google direct (fallback)
     */
    private function selectOptimalProvider(string $context, array $messages): ?AiProvider
    {
        $lastMessage = end($messages)['content'] ?? '';
        $msgLength   = mb_strlen($lastMessage);

        // Tasks nhẹ → model nhỏ nhanh hơn
        $lightContexts = ['classify', 'tag', 'simple_chat'];
        $isLightTask   = in_array($context, $lightContexts) || $msgLength < 50;

        if ($isLightTask) {
            // Ưu tiên OpenRouter GPT-OSS 20B cho tasks nhẹ (nhanh)
            $light = AiProvider::where('is_active', true)
                ->where('model', 'openai/gpt-oss-20b:free')
                ->first();
            if ($light) return $light;
        }

        // Mặc định → OpenRouter Auto (tự chọn model tốt nhất)
        return AiProvider::where('is_default', true)->where('is_active', true)->first()
            ?? AiProvider::where('is_active', true)
                ->where('provider', 'openrouter')
                ->first()
            ?? AiProvider::where('is_active', true)->first();
    }

    /**
     * Fallback tuần tự qua tất cả providers khi model chính bị lỗi
     */
    private function chatWithFallback(array $data, AiProvider $failedProvider): JsonResponse
    {
        // Lấy tất cả providers active, trừ provider vừa fail, theo thứ tự max_tokens giảm dần
        $fallbacks = AiProvider::where('is_active', true)
            ->where('id', '!=', $failedProvider->id)
            ->orderByDesc('max_tokens')
            ->get();

        foreach ($fallbacks as $fallback) {
            try {
                $result = $this->callProvider($fallback, $data['messages']);
                return response()->json([
                    'content'         => $result['content'],
                    'tokens'          => $result['tokens'] ?? 0,
                    'conversation_id' => null,
                    'model_used'      => $fallback->model,
                    'fallback'        => true,
                ]);
            } catch (\Exception $e) {
                \Log::warning("AI fallback failed [{$fallback->model}]: " . $e->getMessage());
                continue; // thử model tiếp theo
            }
        }

        return response()->json([
            'error' => 'Tất cả AI providers đều không khả dụng. Vui lòng thử lại sau vài phút.',
        ], 503);
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
            'openai'      => $this->callOpenAI($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'openrouter'  => $this->callOpenRouter($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'google'      => $this->callGemini($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'anthropic'   => $this->callClaude($apiKey, $provider->model, $messages, $provider->max_tokens, $provider->temperature),
            'ollama'      => $this->callOllama($provider->base_url, $provider->model, $messages, $provider->max_tokens),
            default       => $this->callCustom($provider->base_url, $apiKey, $provider->model, $messages, $provider->max_tokens),
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

    private function callOpenRouter(string $apiKey, string $model, array $messages, int $maxTokens, float $temp): array
    {
        $res = Http::withToken($apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://dtshop.com'),
                'X-Title'      => config('app.name', 'DT Shop Admin'),
            ])
            ->timeout(60)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => $temp,
            ]);

        if (!$res->successful()) {
            throw new \Exception('OpenRouter error: ' . $res->body());
        }

        $json = $res->json();

        // OpenRouter trả về error trong body với status 200 đôi khi
        if (isset($json['error'])) {
            throw new \Exception('OpenRouter error: ' . ($json['error']['message'] ?? json_encode($json['error'])));
        }

        $content = $json['choices'][0]['message']['content'] ?? null;
        if (empty($content)) {
            throw new \Exception('OpenRouter không trả về nội dung');
        }

        return [
            'content' => $content,
            'tokens'  => $json['usage']['total_tokens'] ?? 0,
        ];
    }

    private function callGemini(string $apiKey, string $model, array $messages, int $maxTokens, float $temp): array
    {
        // Gemma models dùng cùng Gemini API endpoint
        // Lọc system message ra khỏi contents (Gemini không hỗ trợ role=system trong contents)
        $systemText = '';
        $contents   = [];

        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemText = $m['content'];
                continue;
            }
            $contents[] = [
                'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ];
        }

        // Gemini yêu cầu messages phải xen kẽ user/model, không được 2 cùng role liên tiếp
        $contents = $this->normalizeGeminiContents($contents);

        $body = [
            'contents'         => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature'     => $temp,
            ],
        ];

        // Thêm system instruction nếu có (Gemini 1.5+ hỗ trợ)
        if ($systemText) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemText]],
            ];
        }

        $res = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", $body);

        if (!$res->successful()) {
            throw new \Exception('Gemini error: ' . $res->body());
        }

        $json = $res->json();

        // Handle blocked/empty response
        if (empty($json['candidates'][0]['content']['parts'][0]['text'])) {
            $reason = $json['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            throw new \Exception("Gemini không trả về nội dung (reason: {$reason})");
        }

        return [
            'content' => $json['candidates'][0]['content']['parts'][0]['text'],
            'tokens'  => $json['usageMetadata']['totalTokenCount'] ?? 0,
        ];
    }

    /**
     * Đảm bảo messages xen kẽ user/model (Gemini requirement)
     * Nếu 2 messages cùng role liên tiếp → merge lại
     */
    private function normalizeGeminiContents(array $contents): array
    {
        if (empty($contents)) {
            return [['role' => 'user', 'parts' => [['text' => 'Hello']]]];
        }

        $normalized = [];
        foreach ($contents as $msg) {
            $last = end($normalized);
            if ($last && $last['role'] === $msg['role']) {
                // Merge cùng role
                $normalized[count($normalized) - 1]['parts'][] = $msg['parts'][0];
            } else {
                $normalized[] = $msg;
            }
        }

        // Phải bắt đầu bằng user
        if ($normalized[0]['role'] !== 'user') {
            array_unshift($normalized, ['role' => 'user', 'parts' => [['text' => '...']]]);
        }

        return $normalized;
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
