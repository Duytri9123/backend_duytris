<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // return Brand::all();
        return BrandResource::collection(Brand::latest()->paginate(10));
    }

    public function store(StoreBrandRequest $request)
    {
        $validatedData = $request->validated();

        if ($request->hasFile('img_url')) {

            $imageFile = $request->file('img_url');

            $newFileName = time() . '-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();

            $path = $imageFile->storeAs('brand_images', $newFileName, 'public');
            $validatedData['img_url'] = $path;
        }
        $brand = Brand::create($validatedData);

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function show(Brand $brand)
    {
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $validatedData = $request->validated();
        if ($request->hasFile('img_url')) {
            $imageFile = $request->file('img_url');

            if ($brand->img_url && Storage::disk('public')->exists($brand->img_url)) {
                Storage::disk('public')->delete($brand->img_url);
            }

            $newFileName = time() . '-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();
            $path = $imageFile->storeAs('brand_images', $newFileName, 'public');
            $validatedData['img_url'] = $path;
        }

        $brand->update($validatedData);

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand)
    {
        if ($brand->products()->exists()) {
            return response()->json(['message' => 'Không thể xóa thương hiệu đang có sản phẩm.'], 400);
        }

        if ($brand->img_url && Storage::disk('public')->exists($brand->img_url)) {
            Storage::disk('public')->delete($brand->img_url);
        }
        $brand->delete();
        return response()->noContent();
    }
}
