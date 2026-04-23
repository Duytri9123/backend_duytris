<?php

use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\BrandsController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ProductAttributeController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ViewProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\AiProviderController;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public settings (frontend dùng)
Route::get('/settings', [SiteSettingController::class, 'index']);
Route::get('/settings/flat', [SiteSettingController::class, 'flat']);

// Routes cần đăng nhập (tất cả user)
Route::middleware(['auth:sanctum'])->group(function () {
    // Get user info - tất cả user đã đăng nhập đều truy cập được
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/products/popular', [ViewProductController::class, 'topViewedProducts']);
    Route::get('/products/{productId}/total-views', [ViewProductController::class, 'getTotalView']);
    // Người dùng tăng lượt xem sản phẩm (giới hạn 15)
    Route::post('/products/{productId}/view', [ViewProductController::class, 'store']);
    // Lấy lượt xem của người dùng hiện tại (nếu có thêm hàm getUserViews)
    Route::get('/user/views', [ViewProductController::class, 'getUserViews']);
});


Route::get('/products', [ProductsController::class, 'index']);
Route::get('/products/{product}', [ProductsController::class, 'show']);

Route::get('/categories', [CategoriesController::class, 'index']);
Route::get('/categories/{category}', [CategoriesController::class, 'show']);
// User có thể xem danh sách và chi tiết, nhưng không thể tạo/sửa/xóa
Route::get('/brands', [BrandsController::class, 'index']);
Route::get('/brands/{brand}', [BrandsController::class, 'show']);


Route::get('/attributes', [ProductAttributeController::class, 'index']);
Route::get('/attributes/{attribute}', [ProductAttributeController::class, 'show']);
Route::get('/attributes/{attribute}/values', [AttributeValueController::class, 'index']);

// Routes chỉ dành cho Admin
// User có thể xem danh sách và chi tiết, nhưng không thể tạo/sửa/xóa

Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {

    // Site Settings
    Route::get('/admin/settings', [SiteSettingController::class, 'adminIndex']);
    Route::put('/admin/settings', [SiteSettingController::class, 'update']);
    Route::post('/admin/settings/upload', [SiteSettingController::class, 'uploadImage']);

    // AI Providers
    Route::apiResource('ai-providers', AiProviderController::class);
    Route::post('/ai-providers/{aiProvider}/test', [AiProviderController::class, 'test']);

    // AI Chat (admin)
    Route::post('/ai/chat', [AiProviderController::class, 'chat']);
    Route::get('/ai/conversations', [AiProviderController::class, 'conversations']);
    Route::apiResource('brands', BrandsController::class)->except(['index', 'show']);;

    // Categories management
    Route::apiResource('categories', CategoriesController::class)->except(['index', 'show']);;

    //     // Products management
    Route::apiResource('products', ProductsController::class)->except(['index', 'show']);

    Route::apiResource('attributes', ProductAttributeController::class)->except(['index', 'show']);

    Route::post('/attributes/{attribute}/values', [AttributeValueController::class, 'store']);
    Route::put('/values/{value}', [AttributeValueController::class, 'update']);
    Route::delete('/values/{value}', [AttributeValueController::class, 'destroy']);
});
