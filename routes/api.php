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
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminComplaintController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SupportTicketReplyController;
use App\Http\Controllers\SupportTicketAdminController;
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

    // User profile update
    Route::put('/user/profile', function (Request $request) {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email,' . $request->user()->id]);
        $request->user()->update($data);
        return response()->json(['data' => $request->user(), 'message' => 'Cập nhật thành công']);
    });

    Route::put('/user/password', function (Request $request) {
        $data = $request->validate(['current_password' => 'required', 'password' => 'required|min:8|confirmed']);
        if (!\Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Mật khẩu hiện tại không đúng'], 422);
        }
        $request->user()->update(['password' => \Hash::make($data['password'])]);
        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    });

    // User notifications — bảng đã tồn tại, bỏ Schema::hasTable check mỗi request
    Route::get('/user/notifications', function (Request $request) {
        $items = \DB::table('user_notifications')
            ->select(['id', 'title', 'message', 'type', 'is_read', 'link', 'created_at'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);
        return response()->json($items);
    });
    Route::post('/user/notifications/{id}/read', function (Request $request, int $id) {
        \DB::table('user_notifications')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['is_read' => true]);
        return response()->json(['message' => 'OK']);
    });
    Route::post('/user/notifications/read-all', function (Request $request) {
        \DB::table('user_notifications')
            ->where('user_id', $request->user()->id)
            ->update(['is_read' => true]);
        return response()->json(['message' => 'OK']);
    });

    // Pusher Beams auth
    Route::get('/push/auth', [\App\Http\Controllers\PushNotificationController::class, 'beamsAuth']);

    Route::get('/products/{productId}/total-views', [ViewProductController::class, 'getTotalView']);
    // Người dùng tăng lượt xem sản phẩm (giới hạn 15)
    Route::post('/products/{productId}/view', [ViewProductController::class, 'store']);
    // Lấy lượt xem của người dùng hiện tại (nếu có thêm hàm getUserViews)
    Route::get('/user/views', [ViewProductController::class, 'getUserViews']);

    // Support Tickets
    Route::post('/support-tickets', [SupportTicketController::class, 'store']);
    Route::get('/support-tickets', [SupportTicketController::class, 'index']);
    Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show']);
    Route::put('/support-tickets/{ticket}', [SupportTicketController::class, 'update']);
    Route::delete('/support-tickets/{ticket}', [SupportTicketController::class, 'destroy']);

    // Support Ticket Replies
    Route::post('/support-tickets/{ticket}/replies', [SupportTicketReplyController::class, 'store']);
    Route::delete('/support-tickets/{ticket}/replies/{reply}', [SupportTicketReplyController::class, 'destroy']);
});


Route::get('/products', [ProductsController::class, 'index']);
Route::get('/products/popular', [ViewProductController::class, 'topViewedProducts']); // public - không cần auth
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

    // Image upload (for Quill editor)
    Route::post('/admin/upload-image', function (\Illuminate\Http\Request $request) {
        $request->validate(['image' => 'required|image|max:5120']);
        $path = $request->file('image')->store('editor', 'public');
        return response()->json(['url' => '/storage/' . $path]);
    });

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

    // Admin Users
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::put('/admin/users/{user}/password', [AdminUserController::class, 'changePassword']);
    Route::post('/admin/users/{user}/ban', [AdminUserController::class, 'ban']);
    Route::post('/admin/users/{user}/unban', [AdminUserController::class, 'unban']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('/admin/users/{user}/orders', [AdminUserController::class, 'orders']);

    // Admin Orders
    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show']);
    Route::put('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    // Admin Complaints
    Route::get('/admin/complaints', [AdminComplaintController::class, 'index']);
    Route::post('/admin/complaints', [AdminComplaintController::class, 'store']);
    Route::get('/admin/complaints/{complaint}', [AdminComplaintController::class, 'show']);
    Route::patch('/admin/complaints/{complaint}/resolve', [AdminComplaintController::class, 'resolve']);
    Route::delete('/admin/complaints/{complaint}', [AdminComplaintController::class, 'destroy']);

    // Admin Support Tickets
    Route::get('/admin/support-tickets', [SupportTicketAdminController::class, 'index']);
    Route::get('/admin/support-tickets/stats', [SupportTicketAdminController::class, 'getStats']);
    Route::get('/admin/support-tickets/{ticket}', [SupportTicketAdminController::class, 'show']);
    Route::put('/admin/support-tickets/{ticket}/status', [SupportTicketAdminController::class, 'updateStatus']);

    // ── Posts / Blog ──────────────────────────────────────────────────────────
    Route::get('/admin/posts',              [\App\Http\Controllers\PostController::class, 'index']);
    Route::post('/admin/posts',             [\App\Http\Controllers\PostController::class, 'store']);
    Route::get('/admin/posts/{post}',       [\App\Http\Controllers\PostController::class, 'show']);
    Route::put('/admin/posts/{post}',       [\App\Http\Controllers\PostController::class, 'update']);
    Route::delete('/admin/posts/{post}',    [\App\Http\Controllers\PostController::class, 'destroy']);

    // ── Banners ───────────────────────────────────────────────────────────────
    Route::get('/admin/banners',            [\App\Http\Controllers\BannerController::class, 'index']);
    Route::post('/admin/banners',           [\App\Http\Controllers\BannerController::class, 'store']);
    Route::put('/admin/banners/{banner}',   [\App\Http\Controllers\BannerController::class, 'update']);
    Route::delete('/admin/banners/{banner}',[\App\Http\Controllers\BannerController::class, 'destroy']);

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::get('/admin/notifications',                          [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/admin/notifications/send',                    [\App\Http\Controllers\NotificationController::class, 'send']);
    Route::post('/admin/notifications/read-all',                [\App\Http\Controllers\NotificationController::class, 'readAll']);
    Route::post('/admin/notifications/{notification}/read',     [\App\Http\Controllers\NotificationController::class, 'markRead']);
    Route::delete('/admin/notifications/{notification}',        [\App\Http\Controllers\NotificationController::class, 'destroy']);

    // ── Broadcast Notifications ───────────────────────────────────────────────
    Route::get('/admin/broadcast',                              [\App\Http\Controllers\BroadcastNotificationController::class, 'index']);
    Route::post('/admin/broadcast',                             [\App\Http\Controllers\BroadcastNotificationController::class, 'store']);
    Route::patch('/admin/broadcast/{id}/toggle',                [\App\Http\Controllers\BroadcastNotificationController::class, 'toggle']);
    Route::delete('/admin/broadcast/{id}',                      [\App\Http\Controllers\BroadcastNotificationController::class, 'destroy']);

    // ── Reviews ───────────────────────────────────────────────────────────────
    Route::get('/reviews',                          [\App\Http\Controllers\ReviewController::class, 'adminIndex']);
    Route::put('/reviews/{review}/approve',         [\App\Http\Controllers\ReviewController::class, 'approve']);
    Route::put('/reviews/{review}/reject',          [\App\Http\Controllers\ReviewController::class, 'reject']);
    Route::put('/reviews/{review}/reply',           [\App\Http\Controllers\ReviewController::class, 'reply']);
    Route::delete('/reviews/{review}',              [\App\Http\Controllers\ReviewController::class, 'destroy']);

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/admin/analytics/summary',  [\App\Http\Controllers\AnalyticsController::class, 'summary']);

    // ── Affiliate Products ────────────────────────────────────────────────────
    Route::get('/admin/affiliate',                    [\App\Http\Controllers\AffiliateProductController::class, 'index']);
    Route::post('/admin/affiliate',                   [\App\Http\Controllers\AffiliateProductController::class, 'store']);
    Route::get('/admin/affiliate/stats',              [\App\Http\Controllers\AffiliateProductController::class, 'stats']);
    Route::post('/admin/affiliate/scrape',            [\App\Http\Controllers\AffiliateProductController::class, 'scrapeFromUrl']);
    Route::get('/admin/affiliate/{id}',               [\App\Http\Controllers\AffiliateProductController::class, 'show']);
    Route::put('/admin/affiliate/{id}',               [\App\Http\Controllers\AffiliateProductController::class, 'update']);
    Route::delete('/admin/affiliate/{id}',            [\App\Http\Controllers\AffiliateProductController::class, 'destroy']);
});

// ── AI Chat cho khách hàng frontend (public, rate limited) ───────────────────
Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/ai/customer-chat', [AiProviderController::class, 'customerChat']);
});

// ── Public routes ─────────────────────────────────────────────────────────────
Route::get('/banners', [\App\Http\Controllers\BannerController::class, 'publicIndex']);
Route::get('/posts', [\App\Http\Controllers\PostController::class, 'publicIndex']);
Route::get('/posts/{slug}', [\App\Http\Controllers\PostController::class, 'publicShow']);
Route::get('/reviews/{productId}', [\App\Http\Controllers\ReviewController::class, 'publicIndex']);

// ── Broadcast notifications (public - không cần đăng nhập) ───────────────────
Route::get('/notifications/broadcast', [\App\Http\Controllers\BroadcastNotificationController::class, 'public']);
Route::get('/affiliate', [\App\Http\Controllers\AffiliateProductController::class, 'index']);
Route::post('/affiliate/{id}/click', [\App\Http\Controllers\AffiliateProductController::class, 'trackClick']);
// Redirect with click tracking
Route::get('/affiliate/{id}/click', function (int $id) {
    $item = \DB::table('affiliate_products')->find($id);
    if (!$item || !$item->is_active) abort(404);
    \DB::table('affiliate_products')->where('id', $id)->increment('click_count');
    return redirect($item->affiliate_url);
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/reviews', [\App\Http\Controllers\ReviewController::class, 'store']);
    // Pusher Beams auth
    Route::get('/push/auth', [\App\Http\Controllers\PushNotificationController::class, 'beamsAuth']);
});

// Admin push notifications
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    Route::post('/admin/push/send', [\App\Http\Controllers\PushNotificationController::class, 'send']);
    Route::post('/admin/push/test', [\App\Http\Controllers\PushNotificationController::class, 'test']);
});
