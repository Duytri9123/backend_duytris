<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
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
            'url' => $this->image,
            'alt_text' => $this->alt_text,
            'display_order' => $this->display_order,
            'is_thumbnail' => (bool) $this->is_thumbnail,
            'product_id' => $this->product_id,
            // Chỉ hiển thị attribute_value_id nếu nó tồn tại (khác null)
            'attribute_value_id' => $this->when($this->attribute_value_id !== null, $this->attribute_value_id),
            'media_type' => $this->media_type,
        ];
    }
}
