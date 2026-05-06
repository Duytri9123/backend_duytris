<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    private function table() { return \DB::table('banners'); }

    public function publicIndex(): JsonResponse
    {
        $banners = $this->table()->where('is_active', true)->orderBy('sort_order')->get();
        return response()->json(['data' => $banners]);
    }

    public function index(): JsonResponse
    {
        $banners = $this->table()->orderBy('sort_order')->get();
        return response()->json(['data' => $banners]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'title'         => 'nullable|string|max:255',
                'subtitle'      => 'nullable|string',
                'link_url'      => 'nullable|string',
                'button_text'   => 'nullable|string|max:50',
                'aspect_ratio'  => ['nullable', 'string', 'regex:/^\d+:\d+$/'],
                'is_active'     => 'nullable|in:0,1,true,false',
                'sort_order'    => 'nullable|integer',
                'image'         => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
                'image_url'     => 'nullable|string|url',
                'text_overlays' => 'nullable|json', // JSON string cho text overlays
                'button_style'  => 'nullable|json', // JSON string cho button styling
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Banner validation failed', [
                'errors' => $e->errors(),
                'request' => $request->except('image'),
                'has_file' => $request->hasFile('image'),
                'file_size' => $request->hasFile('image') ? $request->file('image')->getSize() : null,
            ]);
            throw $e;
        }

        // Ưu tiên: 1. Upload file, 2. URL từ request
        if ($request->hasFile('image')) {
            $data['image_url'] = '/storage/' . $request->file('image')->store('banners', 'public');
            unset($data['image']); // Xóa file object, chỉ giữ image_url
        } elseif (!isset($data['image_url']) || empty($data['image_url'])) {
            // Nếu không có file upload và không có URL, báo lỗi
            return response()->json([
                'message' => 'Vui lòng upload ảnh hoặc nhập URL ảnh',
                'errors' => ['image' => ['Ảnh banner là bắt buộc']]
            ], 422);
        }

        $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $data['sort_order'] = $data['sort_order'] ?? ($this->table()->max('sort_order') + 1);
        $data['aspect_ratio'] = $data['aspect_ratio'] ?? '16:5';

        $id = \DB::table('banners')->insertGetId(array_merge($data, [
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return response()->json(['data' => $this->table()->find($id), 'message' => 'Tạo thành công'], 201);
    }

    public function update(Request $request, int $banner): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'nullable|string|max:255',
            'subtitle'      => 'nullable|string',
            'link_url'      => 'nullable|string',
            'button_text'   => 'nullable|string|max:50',
            'aspect_ratio'  => ['nullable', 'string', 'regex:/^\d+:\d+$/'],
            'is_active'     => 'nullable|in:0,1,true,false',
            'sort_order'    => 'nullable|integer',
            'image'         => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'image_url'     => 'nullable|string|url',
            'text_overlays' => 'nullable|json',
            'button_style'  => 'nullable|json',
        ]);

        // Ưu tiên: 1. Upload file, 2. URL từ request
        if ($request->hasFile('image')) {
            $data['image_url'] = '/storage/' . $request->file('image')->store('banners', 'public');
            unset($data['image']); // Xóa file object, chỉ giữ image_url
        }
        // Nếu có image_url trong request, giữ nguyên (đã validate là URL hợp lệ)

        if (isset($data['is_active'])) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        \DB::table('banners')->where('id', $banner)->update(array_merge($data, ['updated_at' => now()]));
        return response()->json(['data' => $this->table()->find($banner), 'message' => 'Cập nhật thành công']);
    }

    public function destroy(int $banner): JsonResponse
    {
        \DB::table('banners')->where('id', $banner)->delete();
        return response()->json(['message' => 'Đã xóa']);
    }
}
