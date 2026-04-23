<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ViewProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewProductController extends Controller
{
    public function index()
    {

        $view = ViewProduct::get();

        return response()->json($view);
    }
    //Tăng lượt xem của người dùng hiện tại cho một sản phẩm.
    public function store(Request $request, $product_id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $viewProduct = ViewProduct::firstOrCreate(
            ['user_id' => $user->id, 'product_id' => $product_id],
            ['view_count' => 0]
        );

        if ($viewProduct->view_count >= 15) {
            return response()->json(['message' => 'Maximum view limit reached for this product'], 403);
        }

        $viewProduct->increment('view_count');

        return response()->json([
            'message' => 'View count incremented',
            'view_count' => $viewProduct->view_count
        ], 200);
    }
    // Lấy tổng lượt xem của tất cả người dùng cho một sản phẩm.
    public function getTotalView($product_id)
    {
        $totalViews = ViewProduct::where('product_id', $product_id)->sum('view_count');

        return response()->json([
            'product_id' => $product_id,
            'total_views' => $totalViews
        ]);
    }
    //* Lấy danh sách các sản phẩm được xem nhiều nhất.
    public function topViewedProducts()
    {
        $products = ViewProduct::select('product_id', DB::raw('SUM(view_count) as total_views'))
            ->groupBy('product_id')
            ->orderByDesc('total_views')
            ->with([
                'product.brand',
                'product.category',
                'product.product_images'
            ])
            ->get();

        return response()->json($products);
    }
}
