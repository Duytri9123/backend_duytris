<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttributeValueRequest;
use App\Http\Requests\UpdateAttributeValueRequest;
use App\Http\Resources\AttributeValueResource;
use App\Models\AttributeValue;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;

class AttributeValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProductAttribute $attribute)
    {
        return AttributeValueResource::collection($attribute->attributeValues);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeValueRequest $request, ProductAttribute $attribute)
    {
        $value = $attribute->attributeValues()->create($request->validated());

        return (new AttributeValueResource($value))->response()->setStatusCode(201);
    }

    public function show(AttributeValue $value)
    {
        return new AttributeValueResource($value);
    }
    public function update(UpdateAttributeValueRequest $request, AttributeValue $value)
    {
        $value->update($request->validated());
        return new AttributeValueResource($value);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttributeValue $value)
    {
        if ($value->productVariants()->exists()) {
            return response()->json(['message' => 'Không thể xóa giá trị thuộc tính đang được sử dụng.'], 400);
        }
        $value->delete();
        return response()->noContent();
    }
}
