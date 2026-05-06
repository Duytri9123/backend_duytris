<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsController extends Controller
{
    public function summary(): JsonResponse
    {
        // Cache 2 phút — analytics không cần realtime tuyệt đối
        $data = Cache::remember('analytics_summary', 120, function () {
            // 1. Một query duy nhất lấy tất cả stats doanh thu
            $stats = DB::table('orders')
                ->selectRaw("
                    SUM(CASE WHEN status IN ('delivered', 'completed', 'paid') THEN grand_total ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status IN ('delivered', 'completed', 'paid') AND created_at >= ? THEN grand_total ELSE 0 END) as recent_revenue,
                    SUM(CASE WHEN status IN ('delivered', 'completed', 'paid') AND created_at BETWEEN ? AND ? THEN grand_total ELSE 0 END) as last_month_revenue,
                    COUNT(*) as total_orders
                ", [
                    now()->startOfMonth(),
                    now()->subMonth()->startOfMonth(),
                    now()->subMonth()->endOfMonth(),
                ])
                ->first();

            $totalRevenue     = (float) ($stats->total_revenue ?? 0);
            $recentRevenue    = (float) ($stats->recent_revenue ?? 0);
            $lastMonthRevenue = (float) ($stats->last_month_revenue ?? 0);
            $totalOrders      = (int)   ($stats->total_orders ?? 0);

            // 2. Đếm products + users trong 1 query
            $counts = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM products) as total_products,
                    (SELECT COUNT(*) FROM users)    as total_users
            ");
            $totalProducts = (int) ($counts->total_products ?? 0);
            $totalUsers    = (int) ($counts->total_users ?? 0);

            // 3. Đơn hàng theo trạng thái (dùng index trên status)
            $ordersByStatus = DB::table('orders')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            // 4. Top 5 sản phẩm bán chạy — chỉ chạy nếu có cột quantity
            $topProducts = [];
            $hasQuantity = Schema::hasColumn('order_items', 'quantity');
            if ($hasQuantity) {
                $topProducts = DB::table('order_items')
                    ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
                    ->join('products', 'product_variants.product_id', '=', 'products.id')
                    ->select(
                        'products.id',
                        'products.name',
                        DB::raw('SUM(order_items.quantity) as total_sold'),
                        DB::raw('SUM(order_items.price * order_items.quantity) as revenue')
                    )
                    ->groupBy('products.id', 'products.name')
                    ->orderByDesc('total_sold')
                    ->limit(5)
                    ->get();
            }

            // 5. Doanh thu 7 ngày gần nhất (dùng index trên created_at)
            $dailyRevenue = DB::table('orders')
                ->whereIn('status', ['delivered', 'completed', 'paid'])
                ->where('created_at', '>=', now()->subDays(7))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(grand_total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Tăng trưởng doanh thu
            $revenueGrowth = $lastMonthRevenue > 0
                ? round((($recentRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : ($recentRevenue > 0 ? 100 : 0);

            return [
                'total_revenue'      => $totalRevenue,
                'recent_revenue'     => $recentRevenue,
                'last_month_revenue' => $lastMonthRevenue,
                'revenue_growth'     => $revenueGrowth,
                'total_orders'       => $totalOrders,
                'total_products'     => $totalProducts,
                'total_users'        => $totalUsers,
                'orders_by_status'   => $ordersByStatus,
                'top_products'       => $topProducts,
                'daily_revenue'      => $dailyRevenue,
            ];
        });

        return response()->json($data);
    }
}
