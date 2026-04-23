<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'is_admin'   => $this->isAdmin, // từ field isAdmin (cast sang boolean)
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Nếu muốn load thêm quan hệ
            // 'addresses'  => AddressResource::collection($this->whenLoaded('addresses')),
            'cart'       => new CartResource($this->whenLoaded('cart')),
            // 'views'      => ViewProductResource::collection($this->whenLoaded('views')),
        ];
    }
}
