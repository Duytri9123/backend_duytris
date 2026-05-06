<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'replies.user'])->latest();

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

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = (int) $request->input('per_page', 15);
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'replies.user']);
        return response()->json(['data' => $ticket]);
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $data = $validated;
        if ($validated['status'] === 'resolved' && !$ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);

        return response()->json([
            'data' => $ticket,
            'message' => 'Cập nhật trạng thái thành công',
        ]);
    }

    public function getStats()
    {
        // 1. Lấy tất cả counts theo status trong một query
        $statusCounts = SupportTicket::select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // 2. Lấy counts theo category
        $categoryCounts = SupportTicket::select('category', \DB::raw('count(*) as count'))
            ->groupBy('category')
            ->get()
            ->pluck('count', 'category');

        // 3. Lấy counts theo priority
        $priorityCounts = SupportTicket::select('priority', \DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get()
            ->pluck('count', 'priority');

        $stats = [
            'total'       => $statusCounts->sum(),
            'open'        => $statusCounts->get('open', 0),
            'in_progress' => $statusCounts->get('in_progress', 0),
            'resolved'    => $statusCounts->get('resolved', 0),
            'closed'      => $statusCounts->get('closed', 0),
            'by_category' => [
                'complaint' => $categoryCounts->get('complaint', 0),
                'support'   => $categoryCounts->get('support', 0),
                'report'    => $categoryCounts->get('report', 0),
                'feedback'  => $categoryCounts->get('feedback', 0),
            ],
            'by_priority' => [
                'low'    => $priorityCounts->get('low', 0),
                'medium' => $priorityCounts->get('medium', 0),
                'high'   => $priorityCounts->get('high', 0),
                'urgent' => $priorityCounts->get('urgent', 0),
            ],
        ];

        return response()->json(['data' => $stats]);
    }
}
