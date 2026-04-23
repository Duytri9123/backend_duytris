<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Lấy hoặc tạo giỏ hàng cho request hiện tại.
     * Xử lý cho cả người dùng đã đăng nhập và khách chưa đăng nhập.
     */
    public function getOrCreateCart(?string $cartSessionId = null): Cart
    {
        if ($user = Auth::user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        if ($cartSessionId) {
            return Cart::firstOrCreate(['session_id' => $cartSessionId]);
        }

        return Cart::create(['session_id' => Str::uuid()->toString()]);
    }

    /**
     * Hợp nhất giỏ hàng của khách vào giỏ hàng của người dùng khi họ đăng nhập.
     */
    public function mergeGuestCartIntoUserCart(?string $guestCartSessionId, User $user): Cart
    {
        $userCart = $this->getOrCreateCart(); // Lấy giỏ hàng của user

        if ($guestCartSessionId) {
            $guestCart = Cart::where('session_id', $guestCartSessionId)->with('items')->first();
            if ($guestCart) {
                // Chuyển từng item từ giỏ hàng khách sang giỏ hàng của user
                foreach ($guestCart->items as $guestItem) {
                    $this->addItem($userCart, $guestItem->product_variant_id, $guestItem->quantity);
                }
                // Xóa giỏ hàng của khách sau khi đã đăng nhập
                $guestCart->delete();
            }
        }
        return $userCart;
    }

    /**
     * Thêm một sản phẩm vào giỏ hàng.
     */
    public function addItem(Cart $cart, int $variantId, int $quantity = 1): Cart
    {
        $variant = ProductVariant::findOrFail($variantId);
        if ($variant->quantity < $quantity) {
            throw new \Exception('Sản phẩm không đủ số lượng trong kho.');
        }
        $cartItem = $cart->items()->where('product_variant_id', $variantId)->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $quantity;
            if ($variant->quantity < $newQuantity) {
                throw new \Exception('Sản phẩm không đủ số lượng trong kho.');
            }
            $cartItem->increment('quantity', $quantity);
        } else {
            $cart->items()->create([
                'product_variant_id' => $variantId,
                'quantity' => $quantity
            ]);
        }
        return $cart;
    }

    /**
     * Cập nhật số lượng của một item trong giỏ hàng.
     */
    public function updateItemQuantity(Cart $cart, int $cartItemId, int $quantity): Cart
    {
        $cartItem = $cart->items()->findOrFail($cartItemId);
        if ($quantity > 0 && $cartItem->variant->quantity < $quantity) {
            throw new \Exception('Sản phẩm không đủ số lượng trong kho.');
        }

        if ($quantity <= 0) {
            $cartItem->delete();
        } else {
            $cartItem->update(['quantity' => $quantity]);
        }
        return $cart;
    }

    /**
     * Xóa một item khỏi giỏ hàng.
     */
    public function removeItem(Cart $cart, int $cartItemId): Cart
    {
        $cart->items()->where('id', $cartItemId)->delete();
        return $cart;
    }

    /**
     * Xóa toàn bộ sản phẩm trong giỏ hàng.
     */
    public function clearCart(Cart $cart): Cart
    {
        $cart->items()->delete();
        return $cart;
    }
}
