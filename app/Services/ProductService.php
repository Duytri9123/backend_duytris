<?php

namespace App\Services;

use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    public function __construct(
        protected VariantService $variantService,
        protected ImageService $imageService
    ) {}


    public function list(Request $request)
    {
        $query = Product::query()->with([
            'brand',
            'category',
            'thumbnailImage',
            'product_reviews.user',
            'defaultVariant' => function ($q) {
                $q->with('attributeValues.productAttribute');
            },
            'attributeValues.productAttribute',
            'defaultVariant:id,product_id,selling_price,original_price',
            'variants:id,product_id,selling_price,original_price',

        ]);

        // Lọc theo category_id
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        // Lọc theo từ khóa tìm kiếm
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm); // Có thể tìm kiếm cả trong mô tả
            });
        }
        // Thêm các bộ lọc khác nếu cần (ví dụ: theo brand_id, status, giá...)
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sắp xếp
        if ($request->filled('sort_by') && $request->filled('sort_order')) {
            $sortBy = $request->input('sort_by');
            $sortOrder = $request->input('sort_order');
            $allowedSorts = ['name', 'created_at', 'price'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->latest();
        }


        return $query->paginate(12)->withQueryString();
    }

    public function create(array $validatedData, Request $request): Product
    {
        try {
            return DB::transaction(function () use ($validatedData, $request) {

                $product = Product::create(Arr::except($validatedData, ['variants', 'images', 'option_ids']));

                if (isset($validatedData['option_ids'])) {
                    $product->attributeValues()->sync($validatedData['option_ids']);
                }

                if (!empty($validatedData['variants'])) {
                    $this->variantService->sync($product, $validatedData['variants']);
                }

                $product->load('variants.attributeValues');

                if ($request->hasFile('images')) {
                    $this->assignImages(
                        $product,
                        $request->file('images'),
                        $request->input('variants', []),
                        $request->input('thumbnail_image_index'),
                        $request->input('existing_image_thumbnail_id')
                    );
                }
                return $product;
            });
        } catch (Exception $e) {
            Log::error('Lỗi khi tạo sản phẩm', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(Product $product, array $validatedData, Request $request): Product
    {
        try {
            return DB::transaction(function () use ($product, $validatedData, $request) {
                // Update basic product info
                $product->update(Arr::except($validatedData, ['variants', 'new_images', 'deleted_images', 'option_ids']));

                // Update attribute values if provided
                if ($request->has('option_ids')) {
                    $product->attributeValues()->sync($validatedData['option_ids'] ?? []);
                }

                // Delete specified images
                if ($request->filled('deleted_images')) {
                    $this->imageService->deleteManyByIds($request->input('deleted_images'));
                }

                // Update variants
                if ($request->has('variants')) {
                    $this->variantService->sync($product, $validatedData['variants']);
                }

                // Handle image assignments - LOAD FRESH DATA
                if (
                    $request->hasFile('new_images') ||
                    $request->filled('thumbnail_image_index') ||
                    $request->filled('existing_image_thumbnail_id') ||
                    $request->has('variants')
                ) {

                    // Reload product with fresh relationships
                    $product->load(['variants.attributeValues', 'images']);

                    $this->assignImages(
                        $product,
                        $request->file('new_images', []),
                        $request->input('variants', []),
                        $request->input('thumbnail_image_index'),
                        $request->input('existing_image_thumbnail_id')
                    );
                }

                return $product;
            });
        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật sản phẩm', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete(Product $product): void
    {
        try {
            DB::transaction(function () use ($product) {
                $this->imageService->deleteAllForProduct($product);

                $product->delete();
            });
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa sản phẩm', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function assignImages(Product $product, array $uploadedFiles, array $variantsData, ?string $thumbnailImageIndex = null, ?int $existingImageThumbnailId = null): void
    {
        Log::info('assignImages called', [
            'product_id' => $product->id,
            'uploaded_files_count' => count($uploadedFiles),
            'variants_data' => $variantsData,
            'thumbnail_image_index' => $thumbnailImageIndex,
            'existing_image_thumbnail_id' => $existingImageThumbnailId
        ]);

        $assignedImageIndexes = [];

        // Reset thumbnail status for all product images
        $product->images()->update(['is_thumbnail' => false]);

        // Create attribute map for variants
        $attributeMap = $product->variants
            ->mapWithKeys(function ($variant) {
                $key = implode('-', collect($variant->attributeValues->pluck('id'))->sort()->values()->all());
                return [$key => $variant];
            });

        // Process variants and their image assignments
        foreach ($variantsData as $variantData) {
            $attrIds = collect($variantData['attribute_value_ids'] ?? [])->sort()->values()->all();
            $key = implode('-', $attrIds);
            $variantModel = $attributeMap[$key] ?? null;

            if ($variantModel) {
                $imagesRelation = $variantModel->images();

                if (method_exists($imagesRelation, 'detach')) {
                    // Many-to-many relationship
                    $variantModel->images()->detach();
                } else {
                    // One-to-many relationship - update variant_id to null
                    $variantModel->images()->update(['product_variant_id' => null]);
                }

                // Handle existing images for this variant
                if (!empty($variantData['existing_image_ids'])) {
                    foreach ($variantData['existing_image_ids'] as $imageId) {
                        if ($imageId) { // Skip empty values
                            $existingImage = $product->images()->find($imageId);
                            if ($existingImage) {
                                if (method_exists($imagesRelation, 'attach')) {
                                    // Many-to-many relationship
                                    $variantModel->images()->attach($imageId);
                                } else {
                                    // One-to-many relationship
                                    $existingImage->update(['product_variant_id' => $variantModel->id]);
                                }

                                Log::info('Assigned existing image to variant', [
                                    'image_id' => $imageId,
                                    'product_variant_id' => $variantModel->id
                                ]);
                            }
                        }
                    }
                }

                // Handle new images for this variant
                if (!empty($variantData['new_image_indexes'])) {
                    foreach ($variantData['new_image_indexes'] as $imageIndex) {
                        if (isset($uploadedFiles[$imageIndex])) {
                            $isThumbnail = ($thumbnailImageIndex !== null && (string)$imageIndex === $thumbnailImageIndex);

                            // Store new image and assign to variant
                            $newImage = $this->imageService->store(
                                $uploadedFiles[$imageIndex],
                                $product,
                                $variantModel,
                                $isThumbnail
                            );

                            $assignedImageIndexes[] = $imageIndex;

                            Log::info('Created and assigned new image to variant', [
                                'image_id' => $newImage->id,
                                'variant_id' => $variantModel->id,
                                'is_thumbnail' => $isThumbnail
                            ]);
                        }
                    }
                }
            } else {
                Log::warning('Variant not found for attribute combination', [
                    'attribute_key' => $key,
                    'attribute_ids' => $attrIds
                ]);
            }
        }

        // Handle unassigned new images (product-level images)
        foreach ($uploadedFiles as $index => $file) {
            if (!in_array($index, $assignedImageIndexes)) {
                $isThumbnail = ($thumbnailImageIndex !== null && (string)$index === $thumbnailImageIndex);

                $newImage = $this->imageService->store($file, $product, null, $isThumbnail);

                Log::info('Created product-level image', [
                    'image_id' => $newImage->id,
                    'is_thumbnail' => $isThumbnail
                ]);
            }
        }

        // Set thumbnail for existing image if specified
        if ($existingImageThumbnailId !== null) {
            $this->imageService->setThumbnail($existingImageThumbnailId);

            Log::info('Set existing image as thumbnail', [
                'image_id' => $existingImageThumbnailId
            ]);
        }
    }
}
