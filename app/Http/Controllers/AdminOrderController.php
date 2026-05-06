<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user:id,name,email', 'items'])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.productVariant.product', 'paymentMethod']);
        return response()->json(['data' => $order]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $oldStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        // Thông báo cho user khi trạng thái đơn hàng thay đổi
        if ($order->user_id && $oldStatus !== $validated['status']) {
            $statusLabels = [
                'pending'    => '⏳ Chờ xác nhận',
                'processing' => '🔄 Đang xử lý',
                'shipped'    => '🚚 Đang giao hàng',
                'delivered'  => '✅ Đã giao hàng',
                'cancelled'  => '❌ Đã hủy',
            ];
            $label = $statusLabels[$validated['status']] ?? $validated['status'];
            $orderNum = $order->order_number ?? '#' . $order->id;

            NotificationController::createUserNotification(
                $order->user_id,
                $label . ' - Đơn hàng ' . $orderNum,
                'Trạng thái đơn hàng ' . $orderNum . ' đã được cập nhật thành "' . $label . '".',
                'order',
                '/orders/' . $order->id
            );
        }

        return response()->json(['data' => $order, 'message' => 'Cập nhật trạng thái thành công']);
    }
}
