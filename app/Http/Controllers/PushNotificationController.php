<?php

namespace App\Http\Controllers;

use App\Services\PusherBeamsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushNotificationController extends Controller
{
    public function __construct(private PusherBeamsService $beams) {}

    /**
     * Lấy Beams auth token cho user đã đăng nhập
     */
    public function beamsAuth(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $instanceId = config('services.pusher_beams.instance_id');
        $secretKey  = config('services.pusher_beams.secret_key');

        if (!$instanceId || !$secretKey) {
            return response()->json(['error' => 'Pusher Beams not configured'], 503);
        }

        // Generate Beams token for this user
        $expiry = time() + 86400; // 24h
        $payload = base64_encode(json_encode([
            'sub'  => "user-{$userId}",
            'exp'  => $expiry,
            'iss'  => "https://{$instanceId}.pushnotifications.pusher.com",
        ]));

        return response()->json([
            'token' => $payload,
            'user_id' => $userId,
            'interests' => [
                'admin-all',
                "user-{$userId}",
                Auth::user()->isAdmin ?? false ? 'admin-orders' : null,
                Auth::user()->isAdmin ?? false ? 'admin-reviews' : null,
            ],
        ]);
    }

    /**
     * Admin gửi push notification thủ công
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'    => 'required|string|max:100',
            'body'     => 'required|string|max:255',
            'interest' => 'required|string',
            'url'      => 'nullable|string',
        ]);

        $success = $this->beams->sendToInterest(
            $data['interest'],
            $data['title'],
            $data['body'],
            ['url' => $data['url'] ?? '/']
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Đã gửi thông báo' : 'Gửi thất bại',
        ]);
    }

    /**
     * Test push notification
     */
    public function test(): JsonResponse
    {
        $success = $this->beams->sendToInterest('hello', 'Hello từ DT Shop! 👋', 'Push notification đang hoạt động!', [
            'type' => 'test',
            'url'  => '/dashboard',
        ]);

        return response()->json(['success' => $success]);
    }
}
