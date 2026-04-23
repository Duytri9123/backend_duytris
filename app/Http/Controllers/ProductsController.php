<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProductsController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Request $request)
    {
        $products = $this->productService->list($request);
        return ProductResource::collection($products)->response();
    }

    public function store(StoreProductRequest $request)
    {
        try {

            $product = $this->productService->create($request->validated(), $request);

            // Trả về response thành công với mã 201
            return (new ProductResource($product->loadAllRelations()))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Exception $e) {

            return response()->json(['message' => 'Có lỗi xảy ra khi tạo sản phẩm.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500
        }
    }

    public function show(Product $product)
    {
        return (new ProductResource($product->loadAllRelations()))->response();
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        Log::info('ProductsController@update: Request data received', $request->all());
        Log::info('ProductsController@update: Files data received', $request->allFiles());
        // Kiểm tra cụ thể trường thumbnail:
        try {
            $this->productService->update($product, $request->validated(), $request);

            return (new ProductResource($product->loadAllRelations()))->response();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Có lỗi xảy ra khi cập nhật sản phẩm.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function destroy(Product $product)
    {
        try {
            $this->productService->delete($product);
            return response()->noContent();
        } catch (\Exception $e) {

            return response()->json(['message' => 'Có lỗi xảy ra khi xóa sản phẩm.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
