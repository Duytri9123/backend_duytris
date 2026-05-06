<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;

class SupportTicketReplyController extends Controller
{
    public function store(Request $request, SupportTicket $ticket)
    {
        if ($ticket->user_id !== auth()->id() && !auth()->user()->isAdmin) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'attachment_url' => 'nullable|string',
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        $reply->load('user');

        return response()->json([
            'data' => $reply,
            'message' => 'Thêm phản hồi thành công',
        ], 201);
    }

    public function destroy(SupportTicket $ticket, SupportTicketReply $reply)
    {
        if ($reply->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'Phản hồi không thuộc phiếu này'], 404);
        }

        if ($reply->user_id !== auth()->id() && !auth()->user()->isAdmin) {
            return response()->json(['message' => 'Không có quyền xóa'], 403);
        }

        $reply->delete();

        return response()->json(['message' => 'Xóa phản hồi thành công']);
    }
}
