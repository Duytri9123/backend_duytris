<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
public function store(UploadedFile $file, Product $product, ?ProductVariant $variant = null, bool $isThumbnail = false)
{
    // Debug log
    Log::info('ImageService store called', [
        'product_id' => $product->id,
        'variant_id' => $variant?->id,
        'isThumbnail' => $isThumbnail,
        'filename' => $file->getClientOriginalName()
    ]);

    // Xác định loại media
    $type = Str::startsWith($file->getMimeType(), 'image') ? 'image' : 'video';

    // Tạo tên file tùy chỉnh
    $filename = $product->slug . '-' . time() . '-' . Str::random(5) . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('product_media', $filename, 'public');

    $imageData = [
        'image' => $path,
        'alt_text' => $file->getClientOriginalName(),
        'display_order' => $product->images()->count(),
        'media_type' => $type,
        'product_variant_id' => $variant?->id,
        'is_thumbnail' => $isThumbnail,
    ];

    // Debug log
    Log::info('Creating image with data', $imageData);

    $image = $product->images()->create($imageData);

    // Debug log
    Log::info('Image created', [
        'id' => $image->id,
        'is_thumbnail' => $image->is_thumbnail,
        'path' => $image->image
    ]);

    return $image;
}
    public function storeMultiple(array $files, Product $product)
    {
        $createdImages = new Collection();

        // Lặp qua mảng file và tái sử dụng hàm store() cho từng file
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $createdImage = $this->store($file, $product);
                $createdImages->push($createdImage);
            }
        }

        return $createdImages;
    }
    //xóa theo id
    public function deleteManyByIds(array $imageIds): void
    {
        if (empty($imageIds)) {
            return;
        }
        $imagesToDelete = ProductImage::whereIn('id', $imageIds)->get();
        if ($imagesToDelete->isEmpty()) {
            return;
        }
        $pathsToDelete = $imagesToDelete->pluck('image')->filter()->all();
        // Xóa tất cả file vật lý
        if (!empty($pathsToDelete)) {
            Storage::disk('public')->delete($pathsToDelete);
        }
        // Xóa tất cả các bản ghi khỏi CSDL
        ProductImage::destroy($imageIds);
    }

    //xóa tất cả
    public function deleteAllForProduct(Product $product): void
    {
        // Lấy ID của tất cả ảnh thuộc về sản phẩm
        $imageIds = $product->images->pluck('id')->all();

        if (!empty($imageIds)) {
            $this->deleteManyByIds($imageIds);
        }
    }
    public function deleteAllForVariant(ProductVariant $variant): void
    {
        $imageIds = $variant->images->pluck('id')->all();

        if (!empty($imageIds)) {
            $this->deleteManyByIds($imageIds);
        }
    }
     public function setThumbnail(int $imageId): void
    {
        $image = ProductImage::find($imageId);
        if (!$image) {
            return;
        }

        // Hủy đặt thumbnail hiện tại cho sản phẩm
        if ($image->product_id) {
            ProductImage::where('product_id', $image->product_id)
                        ->update(['is_thumbnail' => false]);
        }

        // Đặt ảnh mới làm thumbnail
        $image->update(['is_thumbnail' => true]);
    }
}
