<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    private function table() { return \DB::table('product_reviews'); }

    public function adminIndex(Request $request): JsonResponse
    {
        $q = $this->table();
        if ($s = $request->status) $q->where('status', $s);
        if ($r = $request->rating) $q->where('rating', $r);
        $items = $q->orderByDesc('created_at')->paginate($request->per_page ?? 15);
        return response()->json($items);
    }

    public function publicIndex(int $productId): JsonResponse
    {
        $reviews = $this->table()->where('product_id', $productId)->where('status', 'approved')
            ->orderByDesc('created_at')->paginate(10);
        return response()->json($reviews);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'required|string|max:1000',
        ]);

        $productName = \DB::table('products')->where('id', $data['product_id'])->value('name') ?? 'Sản phẩm';

        $id = \DB::table('product_reviews')->insertGetId([
            'product_id'  => $data['product_id'],
            'user_id'     => Auth::id(),
            'user_name'   => Auth::user()->name,
            'user_email'  => Auth::user()->email,
            'rating'      => $data['rating'],
            'comment'     => $data['comment'],
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Thông báo cho admin
        NotificationController::createAdminNotification(
            '⭐ Đánh giá mới cần duyệt',
            Auth::user()->name . ' đánh giá ' . $data['rating'] . '/5 sao cho "' . $productName . '"',
            'review',
            '/dashboard/reviews'
        );

        return response()->json(['data' => $this->table()->find($id), 'message' => 'Đã gửi đánh giá'], 201);
    }

    public function approve(int $review): JsonResponse
    {
        $row = $this->table()->find($review);
        \DB::table('product_reviews')->where('id', $review)->update(['status' => 'approved', 'updated_at' => now()]);

        // Thông báo cho user khi đánh giá được duyệt
        if ($row && $row->user_id) {
            $productName = \DB::table('products')->where('id', $row->product_id)->value('name') ?? 'Sản phẩm';
            NotificationController::createUserNotification(
                $row->user_id,
                '✅ Đánh giá đã được duyệt',
                'Đánh giá của bạn cho "' . $productName . '" đã được phê duyệt.',
                'review',
                '/products/' . (\DB::table('products')->where('id', $row->product_id)->value('slug') ?? '')
            );
        }

        return response()->json(['message' => 'Đã duyệt']);
    }

    public function reject(int $review): JsonResponse
    {
        $row = $this->table()->find($review);
        \DB::table('product_reviews')->where('id', $review)->update(['status' => 'rejected', 'updated_at' => now()]);

        // Thông báo cho user khi đánh giá bị từ chối
        if ($row && $row->user_id) {
            $productName = \DB::table('products')->where('id', $row->product_id)->value('name') ?? 'Sản phẩm';
            NotificationController::createUserNotification(
                $row->user_id,
                '❌ Đánh giá không được duyệt',
                'Đánh giá của bạn cho "' . $productName . '" không đáp ứng tiêu chuẩn cộng đồng.',
                'review'
            );
        }

        return response()->json(['message' => 'Đã từ chối']);
    }

    public function reply(Request $request, int $review): JsonResponse
    {
        $data = $request->validate(['reply' => 'required|string|max:1000']);
        \DB::table('product_reviews')->where('id', $review)->update(['reply' => $data['reply'], 'updated_at' => now()]);
        return response()->json(['message' => 'Đã phản hồi']);
    }

    public function destroy(int $review): JsonResponse
    {
        \DB::table('product_reviews')->where('id', $review)->delete();
        return response()->json(['message' => 'Đã xóa']);
    }
}
