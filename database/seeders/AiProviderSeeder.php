<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $googleKey     = Crypt::encryptString(env('GOOGLE_AI_API_KEY', ''));
        $openrouterKey = Crypt::encryptString(env('OPENROUTER_API_KEY', ''));

        DB::table('ai_providers')->truncate();

        $viet = 'QUAN TRỌNG: Luôn trả lời bằng tiếng Việt.

Bạn là trợ lý AI của DT Shop. Bạn có thể CAN THIỆP TRỰC TIẾP vào dữ liệu.

Khi người dùng yêu cầu TẠO/SỬA dữ liệu, trả về JSON action trong block ```json``` để hệ thống xử lý:

1. Tạo sản phẩm:
```json
{"type":"create_product","data":{"name":"Tên SP","slug":"ten-sp","description":"Mô tả chi tiết","short_description":"Mô tả ngắn","status":"draft","brand_id":null,"category_id":null}}
```

2. Tạo danh mục:
```json
{"type":"create_category","data":{"name":"Tên DM","slug":"ten-dm","parent_id":null}}
```

3. Tạo bài viết:
```json
{"type":"create_post","data":{"title":"Tiêu đề","slug":"tieu-de","excerpt":"Tóm tắt","content":"Nội dung đầy đủ","status":"draft","category":"Tin tức"}}
```

4. Tạo banner:
```json
{"type":"create_banner","data":{"title":"Tiêu đề","subtitle":"Phụ đề","link_url":"/products","button_text":"Xem ngay","is_active":true}}
```

5. Chuyển trang:
```json
{"type":"navigate","data":{"url":"/dashboard/products"}}
```

Sau JSON, thêm giải thích ngắn bằng tiếng Việt. Nếu chỉ hỏi/tư vấn, không cần JSON.
Phong cách: Ngắn gọn, chuyên nghiệp, tiếng Việt.';

        // ── Kết quả test thực tế ──────────────────────────────────────────────
        // OK:   openrouter/free, google/gemma-4-31b-it:free
        //       nvidia/nemotron-3-super-120b-a12b:free, z-ai/glm-4.5-air:free
        //       nvidia/nemotron-3-nano-30b-a3b:free, nvidia/nemotron-nano-12b-v2-vl:free
        //       baidu/qianfan-ocr-fast:free (image OCR)
        //       gemma-3-27b-it (Google direct)
        // FAIL: meta-llama/llama-3.3-70b (429), qwen3-coder (429)
        //       gpt-oss-120b (503), minimax-m2.5 (503)
        // ─────────────────────────────────────────────────────────────────────

        $providers = [
            // ── 1. AUTO DEFAULT ───────────────────────────────────────────────
            ['name'=>'OpenRouter Auto (Miễn phí)','provider'=>'openrouter','model'=>'openrouter/free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>true,'max_tokens'=>2048,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','product','seo','review','analysis','classify']),
             'system_prompt'=>$viet],

            // ── 2. Gemma 4 31B — mạnh nhất, hỗ trợ ảnh, 262K ctx ─────────────
            ['name'=>'Gemma 4 31B (Vision)','provider'=>'openrouter','model'=>'google/gemma-4-31b-it:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>4096,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','product','seo','analysis','image','long_content']),
             'system_prompt'=>$viet],

            // ── 3. Nemotron 120B — model lớn nhất đang OK ────────────────────
            ['name'=>'Nemotron 120B (NVIDIA)','provider'=>'openrouter','model'=>'nvidia/nemotron-3-super-120b-a12b:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>4096,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','analysis','long_content','code']),
             'system_prompt'=>$viet],

            // ── 4. GLM 4.5 Air — nhanh, 131K ctx ─────────────────────────────
            ['name'=>'GLM 4.5 Air (ZhipuAI)','provider'=>'openrouter','model'=>'z-ai/glm-4.5-air:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>2048,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','classify','summarize','product']),
             'system_prompt'=>$viet],

            // ── 5. Nemotron Nano 30B ──────────────────────────────────────────
            ['name'=>'Nemotron Nano 30B','provider'=>'openrouter','model'=>'nvidia/nemotron-3-nano-30b-a3b:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>2048,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','classify','summarize']),
             'system_prompt'=>$viet],

            // ── 6. Nemotron 12B Vision — hỗ trợ ảnh ─────────────────────────
            ['name'=>'Nemotron 12B Vision','provider'=>'openrouter','model'=>'nvidia/nemotron-nano-12b-v2-vl:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>2048,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','image','classify']),
             'system_prompt'=>$viet],

            // ── 7. Baidu OCR — đọc text từ ảnh ──────────────────────────────
            ['name'=>'Baidu OCR (Đọc ảnh)','provider'=>'openrouter','model'=>'baidu/qianfan-ocr-fast:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>1024,'temperature'=>0.3,
             'capabilities'=>json_encode(['image','ocr']),
             'system_prompt'=>'Đọc và trích xuất text từ ảnh, trả lời bằng tiếng Việt.'],

            // ── 8. Google Gemma 3 27B — Google API trực tiếp (fallback) ──────
            ['name'=>'Gemma 3 27B (Google)','provider'=>'google','model'=>'gemma-3-27b-it',
             'api_key'=>$googleKey,'base_url'=>null,
             'is_active'=>true,'is_default'=>false,'max_tokens'=>2048,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','product','seo','classify']),
             'system_prompt'=>$viet],

            // ── 9. Gemma 3 12B — Google API, nhanh ───────────────────────────
            ['name'=>'Gemma 3 12B (Google)','provider'=>'google','model'=>'gemma-3-12b-it',
             'api_key'=>$googleKey,'base_url'=>null,
             'is_active'=>true,'is_default'=>false,'max_tokens'=>1024,'temperature'=>0.7,
             'capabilities'=>json_encode(['chat','classify','summarize']),
             'system_prompt'=>$viet],

            // ── 10. Gemma 3 4B Vision — đã test OK, hỗ trợ ảnh ──────────────
            ['name'=>'Gemma 3 4B Vision (OpenRouter)','provider'=>'openrouter','model'=>'google/gemma-3-4b-it:free',
             'api_key'=>$openrouterKey,'base_url'=>'https://openrouter.ai/api/v1',
             'is_active'=>true,'is_default'=>false,'max_tokens'=>1024,'temperature'=>0.5,
             'capabilities'=>json_encode(['image','ocr','classify','chat']),
             'system_prompt'=>'Phân tích ảnh và trả lời bằng tiếng Việt. Mô tả chi tiết những gì bạn thấy trong ảnh.'],
        ];

        foreach ($providers as $p) {
            DB::table('ai_providers')->insert(array_merge($p, [
                'created_at'=>now(),'updated_at'=>now(),
            ]));
        }

        $this->command->info('✅ Đã seed ' . count($providers) . ' AI providers (tất cả models free đang hoạt động):');
        $this->command->table(
            ['#','Model','Provider','Ctx','Ảnh','Ghi chú'],
            [
                ['1','openrouter/free',                        'OpenRouter','200K','—', 'DEFAULT — tự chọn tốt nhất'],
                ['2','google/gemma-4-31b-it:free',             'OpenRouter','262K','✅','Mạnh nhất, vision'],
                ['3','nvidia/nemotron-3-super-120b-a12b:free', 'OpenRouter','262K','—', 'Model lớn nhất'],
                ['4','z-ai/glm-4.5-air:free',                  'OpenRouter','131K','—', 'Nhanh'],
                ['5','nvidia/nemotron-3-nano-30b-a3b:free',    'OpenRouter','256K','—', 'Backup'],
                ['6','nvidia/nemotron-nano-12b-v2-vl:free',    'OpenRouter','128K','✅','Vision'],
                ['7','baidu/qianfan-ocr-fast:free',            'OpenRouter','65K', '✅','OCR đọc ảnh'],
                ['8','gemma-3-27b-it',                         'Google',    '131K','—', 'Fallback'],
                ['9','gemma-3-12b-it',                         'Google',    '32K', '—', 'Fallback nhẹ'],
            ]
        );
    }
}
