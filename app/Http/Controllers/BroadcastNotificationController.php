<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BroadcastNotificationController extends Controller
{
    /**
     * PUBLIC: Lấy thông báo broadcast đang active (cho tất cả user, kể cả chưa đăng nhập)
     * Frontend poll endpoint này mỗi 15s
     */
    public function public(Request $request): JsonResponse
    {
        if (!DB::getSchemaBuilder()->hasTable('broadcast_notifications')) {
            return response()->json(['data' => [], 'latest_id' => 0]);
        }

        $since = (int) $request->query('since', 0); // client gửi ID cuối cùng đã nhận

        $query = DB::table('broadcast_notifications')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($since > 0) {
            $query->where('id', '>', $since);
        } else {
            // Lần đầu: chỉ lấy 5 thông báo mới nhất
            $query->orderByDesc('created_at')->limit(5);
        }

        $items = $query->orderByDesc('created_at')->get();
        $latestId = DB::table('broadcast_notifications')->max('id') ?? 0;

        return response()->json([
            'data'      => $items,
            'latest_id' => $latestId,
        ]);
    }

    /**
     * ADMIN: Danh sách tất cả broadcast notifications
     */
    public function index(Request $request): JsonResponse
    {
        $items = DB::table('broadcast_notifications')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($items);
    }

    /**
     * ADMIN: Tạo broadcast notification mới
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'message'    => 'required|string|max:1000',
            'type'       => 'in:info,system,promo,order,product',
            'link'       => 'nullable|string|max:500',
            'color'      => 'nullable|string|max:20',
            'is_active'  => 'boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $id = DB::table('broadcast_notifications')->insertGetId([
            'title'      => $data['title'],
            'message'    => $data['message'],
            'type'       => $data['type'] ?? 'info',
            'link'       => $data['link'] ?? null,
            'color'      => $data['color'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'expires_at' => isset($data['expires_at']) ? now()->parse($data['expires_at']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Đồng thời lưu vào admin_notifications để admin biết
        \DB::table('admin_notifications')->insert([
            'title'            => '📢 Đã gửi broadcast: ' . $data['title'],
            'message'          => $data['message'],
            'type'             => $data['type'] ?? 'info',
            'is_read'          => false,
            'link'             => $data['link'] ?? null,
            'target'           => 'broadcast',
            'recipients_count' => \DB::table('users')->count(), // tất cả user
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'data'    => DB::table('broadcast_notifications')->find($id),
            'message' => 'Đã gửi thông báo đến tất cả người dùng',
        ], 201);
    }

    /**
     * ADMIN: Bật/tắt broadcast notification
     */
    public function toggle(int $id): JsonResponse
    {
        $item = DB::table('broadcast_notifications')->find($id);
        if (!$item) return response()->json(['message' => 'Không tìm thấy'], 404);

        DB::table('broadcast_notifications')
            ->where('id', $id)
            ->update(['is_active' => !$item->is_active, 'updated_at' => now()]);

        return response()->json(['message' => 'Đã cập nhật']);
    }

    /**
     * ADMIN: Xóa broadcast notification
     */
    public function destroy(int $id): JsonResponse
    {
        DB::table('broadcast_notifications')->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa']);
    }
}
