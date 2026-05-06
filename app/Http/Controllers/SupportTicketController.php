<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:complaint,support,report,feedback',
            'priority' => 'required|in:low,medium,high,urgent',
            'attachment_url' => 'nullable|string',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        return response()->json([
            'data' => $ticket,
            'message' => 'Tạo phiếu hỗ trợ thành công',
        ], 201);
    }

    public function index(Request $request)
    {
        $query = SupportTicket::where('user_id', auth()->id())
            ->with('replies.user')
            ->latest();

        if ($category = $request->input('category')) {
            $query->byCategory($category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->byPriority($priority);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    public function show(SupportTicket $ticket)
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $ticket->load('replies.user');
        return response()->json(['data' => $ticket]);
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update($validated);

        return response()->json([
            'data' => $ticket,
            'message' => 'Cập nhật trạng thái thành công',
        ]);
    }

    public function destroy(SupportTicket $ticket)
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $ticket->delete();

        return response()->json(['message' => 'Xóa phiếu hỗ trợ thành công']);
    }
}
