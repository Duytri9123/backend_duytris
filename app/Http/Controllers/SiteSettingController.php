<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    // Public: lấy tất cả settings (frontend dùng)
    public function index(): JsonResponse
    {
        $settings = SiteSetting::all()
            ->groupBy('group')
            ->map(fn($group) => $group->pluck('value', 'key'));

        return response()->json(['data' => $settings]);
    }

    // Public: lấy settings phẳng key=>value
    public function flat(): JsonResponse
    {
        return response()->json(['data' => SiteSetting::all_settings()]);
    }

    // Admin: lấy settings theo group với đầy đủ metadata
    public function adminIndex(): JsonResponse
    {
        $settings = SiteSetting::all()->groupBy('group');
        return response()->json(['data' => $settings]);
    }

    // Admin: cập nhật nhiều settings cùng lúc
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
        ]);

        foreach ($data['settings'] as $item) {
            SiteSetting::set($item['key'], $item['value'] ?? null);
        }

        return response()->json(['message' => 'Cập nhật thành công']);
    }

    // Admin: upload logo/favicon
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'key'   => 'required|in:logo_url,favicon_url,admin_logo_url',
        ]);

        $key = $request->input('key');

        // Xóa ảnh cũ nếu có
        $oldUrl = SiteSetting::get($key);
        if ($oldUrl) {
            $oldPath = str_replace('/storage/', '', parse_url($oldUrl, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('image')->store('site', 'public');
        $url  = '/storage/' . $path;

        SiteSetting::set($key, $url);

        return response()->json(['data' => ['url' => $url], 'message' => 'Upload thành công']);
    }
}
