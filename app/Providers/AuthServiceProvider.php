<?php

namespace App\Providers;

use App\Models\CartItem;
use App\Policies\CartItemPolicy;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CartItem::class => CartItemPolicy::class, // <<--- THÊM DÒNG NÀY
    ];

    public function boot(): void {}
}
