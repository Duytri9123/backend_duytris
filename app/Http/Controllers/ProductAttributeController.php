<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductAttributeRequest;
use App\Http\Requests\UpdateProductAttributeRequest;
use App\Http\Resources\ProductAttributeResource;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;

class ProductAttributeController extends Controller
{
    public function index()
    {
        return ProductAttributeResource::collection(
            ProductAttribute::with('attributeValues')->latest()->get()
        );
    }

    public function store(StoreProductAttributeRequest $request)
    {
        $attribute = ProductAttribute::create($request->validated());
        return (new ProductAttributeResource($attribute))->response()->setStatusCode(201);
    }

    public function show(ProductAttribute $attribute)
    {

        return new ProductAttributeResource($attribute->load('attributeValues'));
    }


    public function update(UpdateProductAttributeRequest $request, ProductAttribute $attribute)
    {
        $attribute->update($request->validated());
        return new ProductAttributeResource($attribute);
    }


    public function destroy(ProductAttribute $attribute)
    {

        if ($attribute->attributeValues()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa loại thuộc tính đang có các giá trị con. Vui lòng xóa các giá trị trước.'
            ], 400);
        }

        $attribute->delete();

        return response()->noContent();
    }
}
