<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CartItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CartItem $cartItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CartItem $cartItem): bool
    {
        // Chỉ cho phép nếu ID người dùng của giỏ hàng khớp với ID người dùng đang đăng nhập
        return $user->id === $cartItem->cart->user_id;
    }

    /**
     * Xác định xem người dùng có thể xóa item trong giỏ hàng không.
     */
    public function delete(User $user, CartItem $cartItem): bool
    {
        return $this->update($user, $cartItem);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CartItem $cartItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CartItem $cartItem): bool
    {
        return false;
    }
}
