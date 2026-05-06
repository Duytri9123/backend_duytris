<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    // ── Admin notifications ───────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('admin_notifications')
            ->select(['id', 'title', 'message', 'type', 'is_read', 'link', 'created_at']);
        if ($request->unread_only) {
            $q->where('is_read', false);
        }
        $items = $q->orderByDesc('created_at')->paginate($request->per_page ?? 20);
        return response()->json($items);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
            'type'    => 'in:order,product,user,review,system,info',
            'target'  => 'in:all,users,admins',
            'link'    => 'nullable|string|max:500',
        ]);

        $target = $data['target'] ?? 'all';
        $recipientsCount = 0;

        // Lưu vào user_notifications cho tất cả user thường
        if (in_array($target, ['all', 'users'])) {
            $userIds = DB::table('users')->where('isAdmin', false)->pluck('id');
            foreach ($userIds as $userId) {
                self::createUserNotification(
                    $userId,
                    $data['title'],
                    $data['message'],
                    $data['type'] ?? 'info',
                    $data['link'] ?? null
                );
            }
            $recipientsCount += $userIds->count();
        }

        // Lưu vào admin_notifications (admin luôn nhận nếu target là all hoặc admins)
        if (in_array($target, ['all', 'admins'])) {
            $adminCount = DB::table('users')->where('isAdmin', true)->count();
            $recipientsCount += $adminCount;
        }

        // Ghi log vào admin_notifications để admin theo dõi
        DB::table('admin_notifications')->insert([
            'title'            => $data['title'],
            'message'          => $data['message'],
            'type'             => $data['type'] ?? 'info',
            'is_read'          => false,
            'link'             => $data['link'] ?? null,
            'target'           => $target,
            'recipients_count' => $recipientsCount,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'message'          => 'Đã gửi thông báo',
            'recipients_count' => $recipientsCount,
            'target'           => $target,
        ]);
    }

    public function readAll(): JsonResponse
    {
        DB::table('admin_notifications')->where('is_read', false)->update(['is_read' => true, 'updated_at' => now()]);
        return response()->json(['message' => 'Đã đánh dấu tất cả đã đọc']);
    }

    public function markRead(int $notification): JsonResponse
    {
        DB::table('admin_notifications')->where('id', $notification)->update(['is_read' => true, 'updated_at' => now()]);
        return response()->json(['message' => 'Đã đánh dấu đã đọc']);
    }

    public function destroy(int $notification): JsonResponse
    {
        DB::table('admin_notifications')->where('id', $notification)->delete();
        return response()->json(['message' => 'Đã xóa']);
    }

    // ── Static helpers (dùng từ các controller khác) ─────────────────────────

    /**
     * Tạo thông báo cho admin
     */
    public static function createAdminNotification(
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null
    ): void {
        try {
            DB::table('admin_notifications')->insert([
                'title'      => $title,
                'message'    => $message,
                'type'       => $type,
                'is_read'    => false,
                'link'       => $link,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create admin notification: ' . $e->getMessage());
        }
    }

    /**
     * Tạo thông báo cho user cụ thể
     */
    public static function createUserNotification(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null
    ): void {
        try {
            DB::table('user_notifications')->insert([
                'user_id'    => $userId,
                'title'      => $title,
                'message'    => $message,
                'type'       => $type,
                'is_read'    => false,
                'link'       => $link,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create user notification: ' . $e->getMessage());
        }
    }
}
