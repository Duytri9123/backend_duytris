<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    private function table() { return \DB::table('posts'); }

    public function index(Request $request): JsonResponse
    {
        $q = $this->table();
        if ($s = $request->search) $q->where('title', 'like', "%$s%");
        if ($st = $request->status) $q->where('status', $st);
        $posts = $q->orderByDesc('created_at')->paginate($request->per_page ?? 12);
        return response()->json($posts);
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $posts = $this->table()->where('status', 'published')
            ->orderByDesc('created_at')->paginate($request->per_page ?? 10);
        return response()->json($posts);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $post = $this->table()->where('slug', $slug)->where('status', 'published')->first();
        if (!$post) return response()->json(['message' => 'Không tìm thấy'], 404);
        \DB::table('posts')->where('id', $post->id)->increment('views_count');
        return response()->json(['data' => $post]);
    }

    public function show(int $post): JsonResponse
    {
        $p = $this->table()->find($post);
        if (!$p) return response()->json(['message' => 'Không tìm thấy'], 404);
        return response()->json(['data' => $p]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255',
            'excerpt'          => 'nullable|string',
            'content'          => 'nullable|string',
            'status'           => 'in:published,draft,archived',
            'category'         => 'nullable|string|max:100',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'thumbnail'        => 'nullable|image|max:5120',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['status'] = $data['status'] ?? 'draft';
        $data['views_count'] = 0;

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_url'] = '/storage/' . $request->file('thumbnail')->store('posts', 'public');
        }

        $id = \DB::table('posts')->insertGetId(array_merge($data, [
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return response()->json(['data' => $this->table()->find($id), 'message' => 'Tạo thành công'], 201);
    }

    public function update(Request $request, int $post): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'string|max:255',
            'slug'             => 'string|max:255',
            'excerpt'          => 'nullable|string',
            'content'          => 'nullable|string',
            'status'           => 'in:published,draft,archived',
            'category'         => 'nullable|string|max:100',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'thumbnail'        => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_url'] = '/storage/' . $request->file('thumbnail')->store('posts', 'public');
        }

        \DB::table('posts')->where('id', $post)->update(array_merge($data, ['updated_at' => now()]));
        return response()->json(['data' => $this->table()->find($post), 'message' => 'Cập nhật thành công']);
    }

    public function destroy(int $post): JsonResponse
    {
        \DB::table('posts')->where('id', $post)->delete();
        return response()->json(['message' => 'Đã xóa']);
    }
}
