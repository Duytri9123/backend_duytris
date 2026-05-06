<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    // ── List ──────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = User::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            if ($role === 'admin')  $query->where('isAdmin', true);
            if ($role === 'user')   $query->where('isAdmin', false);
        }

        if ($status = $request->input('status')) {
            if ($status === 'banned')  $query->where('is_banned', true);
            if ($status === 'active')  $query->where('is_banned', false);
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    // ── Show ──────────────────────────────────────────────────────────────────
    public function show(User $user)
    {
        // Load counts - chỉ load những relation tồn tại
        $user->loadCount(['orders']);
        
        // Thêm reviews_count và products_count nếu có
        try {
            $user->loadCount(['reviews']);
        } catch (\Exception $e) {
            // Reviews table chưa tồn tại
        }
        
        return response()->json(['data' => $user]);
    }

    // ── Update info ───────────────────────────────────────────────────────────
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'email'   => 'sometimes|email|unique:users,email,' . $user->id,
            'isAdmin' => 'sometimes|boolean',
        ]);

        $user->update($validated);
        return response()->json(['data' => $user, 'message' => 'Cập nhật thành công']);
    }

    // ── Change password ───────────────────────────────────────────────────────
    public function changePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($request->password)]);
        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }

    // ── Ban / Unban ───────────────────────────────────────────────────────────
    public function ban(Request $request, User $user)
    {
        if ($user->isAdmin) {
            return response()->json(['message' => 'Không thể vô hiệu hóa tài khoản Admin.'], 422);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user->update([
            'is_banned'     => true,
            'banned_reason' => $request->reason,
            'banned_at'     => now(),
        ]);

        return response()->json(['data' => $user, 'message' => 'Đã vô hiệu hóa tài khoản']);
    }

    public function unban(User $user)
    {
        $user->update([
            'is_banned'     => false,
            'banned_reason' => null,
            'banned_at'     => null,
        ]);

        return response()->json(['data' => $user, 'message' => 'Đã kích hoạt lại tài khoản']);
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    public function destroy(User $user)
    {
        if ($user->isAdmin) {
            return response()->json(['message' => 'Không thể xóa tài khoản Admin.'], 422);
        }

        if ($user->orders()->exists()) {
            return response()->json(['message' => 'Không thể xóa người dùng đang có đơn hàng. Hãy vô hiệu hóa thay thế.'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'Đã xóa tài khoản']);
    }

    // ── Orders ────────────────────────────────────────────────────────────────
    public function orders(User $user, Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $orders = Order::where('user_id', $user->id)->latest()->paginate($perPage);
        return response()->json($orders);
    }
}
