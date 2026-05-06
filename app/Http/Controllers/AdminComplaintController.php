<?php

namespace App\Http\Controllers;

use App\Models\UserComplaint;
use App\Models\User;
use Illuminate\Http\Request;

class AdminComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = UserComplaint::with(['reportedUser:id,name,email', 'reporter:id,name,email', 'order:id,order_number'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('reported_user_id', $userId);
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reported_user_id' => 'required|exists:users,id',
            'reporter_id'      => 'nullable|exists:users,id',
            'order_id'         => 'nullable|exists:orders,id',
            'type'             => 'required|in:spam,fraud,inappropriate,payment_dispute,delivery_issue,other',
            'description'      => 'required|string|max:2000',
        ]);

        $complaint = UserComplaint::create($validated);
        $complaint->load(['reportedUser:id,name,email', 'reporter:id,name,email']);

        return response()->json(['data' => $complaint, 'message' => 'Đã tạo khiếu nại'], 201);
    }

    public function show(UserComplaint $complaint)
    {
        $complaint->load(['reportedUser:id,name,email,is_banned', 'reporter:id,name,email', 'order:id,order_number,status', 'resolvedBy:id,name']);
        return response()->json(['data' => $complaint]);
    }

    public function resolve(Request $request, UserComplaint $complaint)
    {
        $request->validate([
            'status'     => 'required|in:investigating,resolved,dismissed',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $complaint->update([
            'status'      => $request->status,
            'admin_note'  => $request->admin_note,
            'resolved_by' => $request->user()->id,
            'resolved_at' => in_array($request->status, ['resolved', 'dismissed']) ? now() : null,
        ]);

        return response()->json(['data' => $complaint, 'message' => 'Đã cập nhật khiếu nại']);
    }

    public function destroy(UserComplaint $complaint)
    {
        $complaint->delete();
        return response()->json(['message' => 'Đã xóa khiếu nại']);
    }
}
