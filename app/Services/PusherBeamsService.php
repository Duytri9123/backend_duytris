<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PusherBeamsService
{
    private string $instanceId;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->instanceId = config('services.pusher_beams.instance_id', env('PUSHER_BEAMS_INSTANCE_ID', ''));
        $this->secretKey  = config('services.pusher_beams.secret_key', env('PUSHER_BEAMS_SECRET_KEY', ''));
        $this->baseUrl    = "https://{$this->instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->instanceId}/publishes";
    }

    /**
     * Gửi push notification đến một interest
     */
    public function sendToInterest(string $interest, string $title, string $body, array $data = []): bool
    {
        if (empty($this->instanceId) || empty($this->secretKey)) {
            Log::warning('Pusher Beams not configured');
            return false;
        }

        try {
            $res = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl, [
                'interests' => [$interest],
                'web' => [
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                        'icon'  => '/favicon.ico',
                        'data'  => $data,
                    ],
                ],
            ]);

            if ($res->successful()) {
                Log::info("Pusher Beams: sent to interest '{$interest}'");
                return true;
            }

            Log::error('Pusher Beams error: ' . $res->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Pusher Beams exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Gửi thông báo đơn hàng mới
     */
    public function notifyNewOrder(int $orderId, string $customerName, float $amount): bool
    {
        return $this->sendToInterest('admin-orders', '🛒 Đơn hàng mới', "#{$orderId} từ {$customerName} - " . number_format($amount) . '₫', [
            'type'     => 'new_order',
            'order_id' => $orderId,
            'url'      => "/dashboard/orders/{$orderId}",
        ]);
    }

    /**
     * Gửi thông báo đánh giá mới
     */
    public function notifyNewReview(string $productName, int $rating): bool
    {
        return $this->sendToInterest('admin-reviews', '⭐ Đánh giá mới', "{$productName} - {$rating}/5 sao", [
            'type' => 'new_review',
            'url'  => '/dashboard/reviews',
        ]);
    }

    /**
     * Gửi thông báo tùy chỉnh đến tất cả admin
     */
    public function notifyAdmins(string $title, string $body, array $data = []): bool
    {
        return $this->sendToInterest('admin-all', $title, $body, $data);
    }

    /**
     * Gửi thông báo đến user cụ thể
     */
    public function notifyUser(int $userId, string $title, string $body, array $data = []): bool
    {
        return $this->sendToInterest("user-{$userId}", $title, $body, $data);
    }
}
