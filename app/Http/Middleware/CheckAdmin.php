<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Kiểm tra xem user đã đăng nhập chưa
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
                'error_code' => 'AUTH_REQUIRED'
            ], 401);
        }

        // Kiểm tra xem user có phải admin không
        if (!Auth::user()->isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.'
            ], 403);
        }
        return $next($request);
    }
}
