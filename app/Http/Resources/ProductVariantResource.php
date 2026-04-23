<?php

namespace App\Http\Resources;

use App\Models\ProductAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $allProductImages = $this->product->images->pluck('id')->toArray();
        $variantImageIds = $this->whenLoaded('images', function () {
            return $this->images->pluck('id')->toArray();
        }, []);

        $imageIndexes = [];
        foreach ($variantImageIds as $variantImageId) {
            $index = array_search($variantImageId, $allProductImages);
            if ($index !== false) {
                $imageIndexes[] = $index;
            }
        }
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'selling_price' => $this->selling_price,
            'original_price' => $this->original_price,
            'dimensions' => $this->dimensions,
            'weight' => $this->weight,
            'quantity' => $this->quantity,
            'is_default' => $this->is_default,

            // Hiển thị danh sách các thuộc tính đã định nghĩa nên biến thể này
            'attribute_values' => AttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'image_indexes' => $imageIndexes,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),

        ];
    }
}
