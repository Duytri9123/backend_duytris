<?php

namespace App\Services;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class SkuGeneratorService
{
    /**
     * Tạo SKU duy nhất cho một biến thể dựa trên sản phẩm cha và các ID thuộc tính.
     */
    public function generate(Product $product, array $attributeValueIds): string
    {
        $productCode = strtoupper(substr($product->slug, 0, 3));


        $attributeCodes = AttributeValue::whereIn('id', $attributeValueIds)
                            ->orderBy('code', 'asc')
                            ->pluck('code')
                            ->map(fn($code) => strtoupper($code))
                            ->all();

        $attributeString = implode('-', $attributeCodes);

        $baseSku = $productCode . '-' . $attributeString;
        $finalSku = $baseSku;
        $counter = 1;

        // 3. Đảm bảo SKU là duy nhất
        while (ProductVariant::where('sku', $finalSku)->exists()) {
            $finalSku = $baseSku . '-' . $counter++;
        }

        return $finalSku;
    }
}
