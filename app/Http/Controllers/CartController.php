<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class CartController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CartService $cartService) {}

    private function getCartSessionId(Request $request): ?string
    {
        return $request->header('X-Cart-Session-ID');
    }

    public function show(Request $request)
    {
        $cart = $this->cartService->getOrCreateCart($this->getCartSessionId($request));
        $cart->load('items.variant.product.thumbnailImage', 'items.variant.attributeValues.productAttribute');

        $response = new CartResource($cart);
        // Nếu là khách và giỏ hàng vừa được tạo, trả về session_id mới để client lưu lại
        if (!Auth::check() && !$this->getCartSessionId($request)) {
            return $response->additional(['meta' => ['cart_session_id' => $cart->session_id]]);
        }
        return $response;
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,id',
            'quantity'   => 'sometimes|integer|min:1|max:100',
        ]);

        $cart = $this->cartService->getOrCreateCart($this->getCartSessionId($request));
        $this->cartService->addItem($cart, $validated['variant_id'], $validated['quantity'] ?? 1);

        $cart->load('items.variant.product.thumbnailImage', 'items.variant.attributeValues.productAttribute');
        return new CartResource($cart);
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $this->authorize('update', $cartItem);

        $validated = $request->validate(['quantity' => 'required|integer|min:0']);

        /** @var \App\Models\Cart $cart */
        $cart = $cartItem->cart;

        $this->cartService->updateItemQuantity($cart, $cartItem->id, $validated['quantity']);

        // Dùng `fresh()` để lấy trạng thái mới nhất của giỏ hàng
        return new CartResource($cart->fresh()->load('items'));
    }

    public function removeItem(CartItem $cartItem)
    {
        $this->authorize('delete', $cartItem);

        /** @var \App\Models\Cart $cart */
        $cart = $cartItem->cart;

        $this->cartService->removeItem($cart, $cartItem->id);

        return new CartResource($cart->fresh()->load('items'));
    }

    public function clear(Request $request)
    {
        $cart = $this->cartService->getOrCreateCart($this->getCartSessionId($request));

        if (Auth::check() && $cart->user_id !== Auth::id()) {
            abort(403);
        }

        $this->cartService->clearCart($cart);
        return response()->json(['message' => 'Giỏ hàng đã được xóa.']);
    }
}
