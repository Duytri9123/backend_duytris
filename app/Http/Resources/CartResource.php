<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtotal = $this->items->reduce(fn($c, $i) => $i->variant ? $c + ($i->variant->selling_price * $i->quantity) : $c, 0);
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->items->count(),
            'total_quantity' => $this->items->sum('quantity'),
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ];
    }
}
