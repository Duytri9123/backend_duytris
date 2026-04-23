<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            // Lấy giá trị chuỗi của Enum để trả về qua API
            'status' => $this->status->value,
            'views' => $this->views,
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Mối quan hệ danh sách (hasMany, belongsToMany)
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'thumbnail_image' => new ProductImageResource($this->whenLoaded('thumbnailImage')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),

            'avg_rating' => round($this->avg_rating ?? 0, 1),
            'rating_count' => $this->rating_count ?? 0,
        ];
    }
}
