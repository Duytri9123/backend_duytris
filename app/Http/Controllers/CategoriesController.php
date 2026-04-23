<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Support\Str;

class CategoriesController extends Controller
{

    public function index()
    {

        $categories = Category::whereNull('parent_id')->with('allChildren')->latest()->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $validatedData = $request->validated();

        if ($request->hasFile('img_url')) {
            $imageFile = $request->file('img_url');
            $newFileName = time() . '-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();

            $path = $imageFile->storeAs('category_images', $newFileName, 'public');
            $validatedData['img_url'] = $path;
        }

        $category = Category::create($validatedData);


        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category)
    {
        $category->load(['parent', 'children']);
        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validatedData = $request->validated();
        if ($request->hasFile('img_url')) {

            if ($category->img_url && Storage::disk('public')->exists($category->img_url)) {
                Storage::disk('public')->delete($category->img_url);
            }

            $imageFile = $request->file('img_url');
            // Tạo tên file mới và lưu
            $newFileName = time() . '-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();
            $path = $imageFile->storeAs('category_images', $newFileName, 'public');
            $validatedData['img_url'] = $path;
        }
        $category->update($validatedData);

        return new CategoryResource($category);
    }


    public function destroy(Category $category)
    {
        // Kiểm tra xem có danh mục con không
        if ($category->children()->exists()) {
            return response()->json(['message' => 'Không thể xóa danh mục có chứa danh mục con.'], 400);
        }
        // Kiểm tra xem có sản phẩm nào thuộc danh mục không
        if ($category->products()->exists()) {
            return response()->json(['message' => 'Không thể xóa danh mục có chứa sản phẩm.'], 400);
        }
        // Xóa ảnh khỏi storage
        if ($category->img_url) {
            Storage::disk('public')->delete($category->img_url);
        }

        $category->delete();

        return response()->noContent();
    }
}
