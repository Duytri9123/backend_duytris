<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AffiliateProductController extends Controller
{
    private function table() { return \DB::table('affiliate_products'); }

    public function index(Request $request): JsonResponse
    {
        $q = $this->table();
        if ($s = $request->search) {
            $q->where(function($q) use ($s) {
                $q->where('name', 'like', "%$s%")->orWhere('brand', 'like', "%$s%");
            });
        }
        if ($p = $request->platform) $q->where('platform', $p);
        if ($request->featured) $q->where('is_featured', true);
        if ($request->active !== null) $q->where('is_active', (bool)$request->active);

        $items = $q->orderByDesc('created_at')->paginate($request->per_page ?? 20);
        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string',
            'image_url'           => 'nullable|string',
            'price'               => 'nullable|numeric|min:0',
            'original_price'      => 'nullable|numeric|min:0',
            'affiliate_url'       => 'required|string',
            'platform'            => 'required|in:shopee,lazada,tiki,tiktok,amazon,sendo,custom',
            'platform_product_id' => 'nullable|string',
            'category'            => 'nullable|string|max:100',
            'brand'               => 'nullable|string|max:100',
            'commission_rate'     => 'nullable|numeric|min:0|max:100',
            'is_active'           => 'boolean',
            'is_featured'         => 'boolean',
            'tags'                => 'nullable|array',
        ]);

        if (isset($data['tags'])) $data['tags'] = json_encode($data['tags']);

        $id = $this->table()->insertGetId(array_merge($data, [
            'click_count'      => 0,
            'order_count'      => 0,
            'total_commission' => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]));

        return response()->json(['data' => $this->table()->find($id), 'message' => 'Tạo thành công'], 201);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->table()->find($id);
        if (!$item) return response()->json(['message' => 'Không tìm thấy'], 404);
        return response()->json(['data' => $item]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'string|max:255',
            'description'         => 'nullable|string',
            'image_url'           => 'nullable|string',
            'price'               => 'nullable|numeric|min:0',
            'original_price'      => 'nullable|numeric|min:0',
            'affiliate_url'       => 'string',
            'platform'            => 'in:shopee,lazada,tiki,tiktok,amazon,sendo,custom',
            'platform_product_id' => 'nullable|string',
            'category'            => 'nullable|string|max:100',
            'brand'               => 'nullable|string|max:100',
            'commission_rate'     => 'nullable|numeric|min:0|max:100',
            'is_active'           => 'boolean',
            'is_featured'         => 'boolean',
            'tags'                => 'nullable|array',
        ]);

        if (isset($data['tags'])) $data['tags'] = json_encode($data['tags']);

        $this->table()->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        return response()->json(['data' => $this->table()->find($id), 'message' => 'Cập nhật thành công']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->table()->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa']);
    }

    /** Track click — public endpoint */
    public function trackClick(int $id): JsonResponse
    {
        $item = $this->table()->find($id);
        if (!$item) return response()->json(['message' => 'Không tìm thấy'], 404);

        $this->table()->where('id', $id)->increment('click_count');

        return response()->json([
            'affiliate_url' => $item->affiliate_url,
            'message'       => 'Click tracked',
        ]);
    }

    /** Stats summary */
    public function stats(): JsonResponse
    {
        $stats = $this->table()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(click_count) as total_clicks,
                SUM(order_count) as total_orders,
                SUM(total_commission) as total_commission
            ")
            ->first();

        $byPlatform = $this->table()
            ->select('platform', \DB::raw('count(*) as count'), \DB::raw('sum(click_count) as clicks'))
            ->groupBy('platform')
            ->get();

        return response()->json([
            'total'            => (int) ($stats->total ?? 0),
            'active'           => (int) ($stats->active ?? 0),
            'total_clicks'     => (int) ($stats->total_clicks ?? 0),
            'total_orders'     => (int) ($stats->total_orders ?? 0),
            'total_commission' => (float) ($stats->total_commission ?? 0),
            'by_platform'      => $byPlatform,
        ]);
    }

    /**
     * Scrape product info from a URL.
     * Strategy: fetch HTML → parse Open Graph, JSON-LD, and platform-specific meta tags.
     */
    public function scrapeFromUrl(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|url']);
        $url  = $data['url'];

        $platform = $this->detectPlatform($url);

        try {
            $html = $this->fetchHtml($url);
        } catch (\Exception $e) {
            Log::warning('Affiliate scrape fetch failed: ' . $e->getMessage());
            return response()->json([
                'platform'      => $platform,
                'affiliate_url' => $url,
                'name'          => '',
                'description'   => '',
                'price'         => null,
                'original_price'=> null,
                'image_url'     => null,
                'brand'         => null,
                'category'      => null,
                'error'         => 'Không thể tải trang. Vui lòng điền thông tin thủ công.',
            ], 200);
        }

        $result = $this->parseProductInfo($html, $url, $platform);

        return response()->json($result);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function detectPlatform(string $url): string
    {
        return match(true) {
            str_contains($url, 'shopee.vn')  => 'shopee',
            str_contains($url, 'lazada.vn')  => 'lazada',
            str_contains($url, 'tiki.vn')    => 'tiki',
            str_contains($url, 'tiktok.com') => 'tiktok',
            str_contains($url, 'amazon.')    => 'amazon',
            str_contains($url, 'sendo.vn')   => 'sendo',
            default                          => 'custom',
        };
    }

    private function fetchHtml(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate',
            'Cache-Control'   => 'no-cache',
        ])
        ->timeout(15)
        ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function parseProductInfo(string $html, string $url, string $platform): array
    {
        $result = [
            'platform'       => $platform,
            'affiliate_url'  => $url,
            'name'           => null,
            'description'    => null,
            'price'          => null,
            'original_price' => null,
            'image_url'      => null,
            'brand'          => null,
            'category'       => null,
        ];

        // 1. JSON-LD (most reliable — used by Tiki, Sendo, many shops)
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $ld = json_decode(trim($json), true);
                if (!$ld) continue;

                // Handle @graph array
                $items = isset($ld['@graph']) ? $ld['@graph'] : [$ld];
                foreach ($items as $node) {
                    $type = $node['@type'] ?? '';
                    if (!in_array($type, ['Product', 'product'])) continue;

                    $result['name']        = $result['name']        ?? ($node['name'] ?? null);
                    $result['description'] = $result['description'] ?? ($node['description'] ?? null);
                    $result['brand']       = $result['brand']       ?? ($node['brand']['name'] ?? $node['brand'] ?? null);

                    // Image
                    if (empty($result['image_url'])) {
                        $img = $node['image'] ?? null;
                        if (is_array($img)) $img = $img[0] ?? null;
                        if (is_array($img)) $img = $img['url'] ?? null;
                        $result['image_url'] = $img;
                    }

                    // Price from offers
                    if (empty($result['price'])) {
                        $offers = $node['offers'] ?? null;
                        if ($offers) {
                            if (isset($offers['@type'])) $offers = [$offers]; // single offer
                            foreach ((array)$offers as $offer) {
                                $price = $offer['price'] ?? $offer['lowPrice'] ?? null;
                                if ($price) {
                                    $result['price'] = $this->parsePrice($price);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Open Graph tags (fallback — works on most platforms)
        $og = $this->parseOpenGraph($html);

        $result['name']        = $result['name']        ?? ($og['og:title'] ?? $og['title'] ?? null);
        $result['description'] = $result['description'] ?? ($og['og:description'] ?? $og['description'] ?? null);
        $result['image_url']   = $result['image_url']   ?? ($og['og:image'] ?? null);

        // OG price tags (some platforms use these)
        if (empty($result['price'])) {
            $ogPrice = $og['product:price:amount'] ?? $og['og:price:amount'] ?? null;
            if ($ogPrice) $result['price'] = $this->parsePrice($ogPrice);
        }

        // 3. Platform-specific parsing
        $result = match($platform) {
            'shopee' => $this->parseShopee($html, $result),
            'lazada' => $this->parseLazada($html, $result),
            'tiki'   => $this->parseTiki($html, $result),
            default  => $result,
        };

        // 4. Clean up
        $result['name']        = $result['name']        ? $this->cleanText($result['name'])        : null;
        $result['description'] = $result['description'] ? $this->cleanText($result['description']) : null;
        $result['brand']       = $result['brand']       ? $this->cleanText($result['brand'])       : null;

        return $result;
    }

    private function parseOpenGraph(string $html): array
    {
        $tags = [];

        // <meta property="og:..." content="...">
        preg_match_all('/<meta[^>]+property=["\']([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $m);
        foreach ($m[1] as $i => $prop) {
            $tags[$prop] = html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // <meta name="..." content="...">
        preg_match_all('/<meta[^>]+name=["\']([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $m);
        foreach ($m[1] as $i => $name) {
            $tags[$name] = html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // <title>
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $tags['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $tags;
    }

    private function parseShopee(string $html, array $result): array
    {
        // Shopee embeds product data in window.__NEXT_DATA__ or window.pageData
        if (preg_match('/window\.__NEXT_DATA__\s*=\s*(\{.+?\});\s*<\/script>/s', $html, $m)) {
            $nextData = json_decode($m[1], true);
            $item = $nextData['props']['pageProps']['initialState']['pdpReducer']['itemInfo']['item']
                 ?? $nextData['props']['pageProps']['product']
                 ?? null;

            if ($item) {
                $result['name']           = $result['name']           ?? ($item['name'] ?? null);
                $result['description']    = $result['description']    ?? ($item['description'] ?? null);
                $result['brand']          = $result['brand']          ?? ($item['brand'] ?? null);
                $result['price']          = $result['price']          ?? ($item['price'] ? $item['price'] / 100000 : null);
                $result['original_price'] = $result['original_price'] ?? ($item['price_before_discount'] ? $item['price_before_discount'] / 100000 : null);

                if (empty($result['image_url']) && !empty($item['image'])) {
                    $result['image_url'] = 'https://cf.shopee.vn/file/' . $item['image'];
                }
            }
        }

        // Shopee short links (s.shopee.vn) — just return what we have from OG
        return $result;
    }

    private function parseLazada(string $html, array $result): array
    {
        // Lazada embeds data in window.pageData
        if (preg_match('/window\.pageData\s*=\s*(\{.+?\});\s*(?:window|<\/script>)/s', $html, $m)) {
            $pageData = json_decode($m[1], true);
            $mods = $pageData['mods'] ?? [];

            $productInfo = $mods['productInfo']['data'] ?? null;
            if ($productInfo) {
                $result['name']  = $result['name']  ?? ($productInfo['name'] ?? null);
                $result['brand'] = $result['brand'] ?? ($productInfo['brand'] ?? null);
            }

            $price = $mods['price']['data'] ?? null;
            if ($price) {
                $result['price']          = $result['price']          ?? ($price['price'] ?? null);
                $result['original_price'] = $result['original_price'] ?? ($price['originalPrice'] ?? null);
            }
        }

        return $result;
    }

    private function parseTiki(string $html, array $result): array
    {
        // Tiki uses JSON-LD well, but also has __TIKI_STATE__
        if (preg_match('/window\.__TIKI_STATE__\s*=\s*(\{.+?\});\s*<\/script>/s', $html, $m)) {
            $state = json_decode($m[1], true);
            $product = $state['product']['data'] ?? null;

            if ($product) {
                $result['name']           = $result['name']           ?? ($product['name'] ?? null);
                $result['brand']          = $result['brand']          ?? ($product['brand']['name'] ?? null);
                $result['price']          = $result['price']          ?? ($product['price'] ?? null);
                $result['original_price'] = $result['original_price'] ?? ($product['list_price'] ?? null);
                $result['category']       = $result['category']       ?? ($product['categories'][0]['name'] ?? null);

                if (empty($result['image_url'])) {
                    $result['image_url'] = $product['thumbnail_url'] ?? $product['images'][0]['base_url'] ?? null;
                }
            }
        }

        return $result;
    }

    private function parsePrice(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') return null;
        // Remove currency symbols, spaces, dots used as thousand separators
        $cleaned = preg_replace('/[^\d,.]/', '', (string)$raw);
        // Handle Vietnamese format: 200.000 or 200,000
        $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
        $val = (float) $cleaned;
        return $val > 0 ? $val : null;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }
}
