<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')->id;
        $product = Product::find($productId);
        $existingImagesCount = $product ? $product->images()->count() : 0;

        $rules = [
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'description'   => 'nullable|string',
            'short_description' => 'nullable|string|max:400',
            'brand_id'      => 'sometimes|required|exists:brands,id',
            'category_id'   => 'sometimes|required|exists:categories,id',
            'status'        => ['sometimes', 'required', new Enum(ProductStatus::class)],
            'variants'      => 'sometimes|array',
            'new_images'        => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    $videoCount = 0;
                    foreach ($value as $file) {
                        if (str_starts_with($file->getMimeType(), 'video')) {
                            $videoCount++;
                        }
                    }
                    if ($videoCount > 1) {
                        $fail('Chỉ được phép upload tối đa 1 video cho mỗi sản phẩm.');
                    }
                }
            ],
            'new_images.*'      => ['required', 'file', 'mimetypes:image/jpeg,image/png,video/mp4,video/quicktime', 'max:20480'],
            'deleted_images'   => 'nullable|array',
            'deleted_images.*' => 'integer|exists:product_images,id,product_id,' . $productId,
            'option_ids'   => 'sometimes|array',
            'option_ids.*' => 'required|exists:attribute_values,id',

            'existing_image_thumbnail_id' => 'nullable|integer|exists:product_images,id',
            'thumbnail_image_index' => 'nullable|integer',
        ];

        foreach ($this->input('variants', []) as $index => $variantData) {
            $variantId = $variantData['id'] ?? null;

            $rules["variants.{$index}.id"]               = 'nullable|exists:product_variants,id';
            $rules["variants.{$index}.sku"]              = ['nullable', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variantId)];
            $rules["variants.{$index}.selling_price"]    = 'required|numeric|min:0';
            $rules["variants.{$index}.original_price"]   = 'nullable|numeric|min:0|gte:variants.' . $index . '.selling_price';
            $rules["variants.{$index}.quantity"]         = 'required|integer|min:0';
            $rules["variants.{$index}.weight"]         = 'nullable|min:0';
            $rules["variants.{$index}.dimensions"]         = 'nullable|string|max:255';
            $rules["variants.{$index}.is_default"]       = 'required|boolean';
            $rules["variants.{$index}.attribute_value_ids"] = 'required|array|min:1';
            $rules["variants.{$index}.attribute_value_ids.*"] = 'required|exists:attribute_values,id';

            // Validation cho existing image IDs
            $rules["variants.{$index}.existing_image_ids"] = 'nullable|array';
            $rules["variants.{$index}.existing_image_ids.*"] = [
                'nullable',
                'integer',
                Rule::exists('product_images', 'id')->where('product_id', $productId)
            ];

            // Validation cho new image indexes
            $rules["variants.{$index}.new_image_indexes"] = [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (!$value) return;

                    $newImagesCount = count($this->file('new_images') ?? []);

                    foreach ($value as $imgIndex) {
                        if (!is_numeric($imgIndex) || $imgIndex < 0 || $imgIndex >= $newImagesCount) {
                            $fail("Chỉ số ảnh mới {$imgIndex} không hợp lệ. Chỉ số phải từ 0 đến " . ($newImagesCount - 1) . ".");
                        }
                    }
                }
            ];
            $rules["variants.{$index}.new_image_indexes.*"] = 'integer|min:0';

            // DEPRECATED: Keep old validation for backward compatibility (optional)
            $rules["variants.{$index}.image_indexes"] = 'nullable|array';
            $rules["variants.{$index}.image_indexes.*"] = 'integer';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required'                             => 'Tên sản phẩm là bắt buộc.',
            'name.string'                               => 'Tên sản phẩm phải là chuỗi ký tự.',
            'name.max'                                  => 'Tên sản phẩm không được vượt quá 255 ký tự.',
            'description.string'                        => 'Mô tả phải là chuỗi ký tự.',
            'short_description.string'                  => 'Mô tả ngắn phải là chuỗi ký tự.',
            'short_description.max'                     => 'Mô tả ngắn không được vượt quá 400 ký tự.',
            'brand_id.required'                         => 'Vui lòng chọn thương hiệu.',
            'brand_id.exists'                           => 'Thương hiệu không tồn tại.',
            'category_id.required'                      => 'Vui lòng chọn danh mục.',
            'category_id.exists'                        => 'Danh mục không tồn tại.',
            'status.required'                           => 'Trạng thái sản phẩm là bắt buộc.',
            'status.Illuminate\Validation\Rules\Enum'   => 'Trạng thái sản phẩm không hợp lệ.',

            'variants.sometimes'                        => 'Biến thể là tùy chọn để cập nhật.',
            'variants.array'                            => 'Biến thể phải là một mảng.',

            'variants.*.sku.string'                     => 'SKU của biến thể phải là chuỗi ký tự.',
            'variants.*.sku.max'                        => 'SKU của biến thể không được vượt quá 255 ký tự.',
            'variants.*.sku.unique'                     => 'SKU ":input" đã tồn tại.',

            'variants.*.selling_price.required'         => 'Giá bán của biến thể là bắt buộc.',
            'variants.*.selling_price.numeric'          => 'Giá bán của biến thể phải là số.',
            'variants.*.selling_price.min'              => 'Giá bán của biến thể phải lớn hơn hoặc bằng 0.',

            'variants.*.original_price.numeric'         => 'Giá gốc của biến thể phải là số.',
            'variants.*.original_price.min'             => 'Giá gốc của biến thể phải lớn hơn hoặc bằng 0.',
            'variants.*.original_price.gte'             => 'Giá gốc của biến thể phải lớn hơn hoặc bằng giá bán.',

            'variants.*.quantity.required'              => 'Số lượng của biến thể là bắt buộc.',
            'variants.*.quantity.integer'               => 'Số lượng của biến thể phải là số nguyên.',
            'variants.*.quantity.min'                   => 'Số lượng của biến thể phải lớn hơn hoặc bằng 0.',

            'variants.*.is_default.required'            => 'Trạng thái mặc định của biến thể là bắt buộc.',
            'variants.*.is_default.boolean'             => 'Trạng thái mặc định của biến thể không hợp lệ.',

            'variants.*.dimensions.string'              => 'Kích thước của biến thể phải là chuỗi ký tự.',
            'variants.*.dimensions.max'                 => 'Kích thước của biến thể không được vượt quá 255 ký tự.',

            'variants.*.weight.numeric'                 => 'Cân nặng của biến thể phải là số.',
            'variants.*.weight.min'                     => 'Cân nặng của biến thể phải lớn hơn hoặc bằng 0.',

            'variants.*.attribute_value_ids.required'   => 'Các giá trị thuộc tính cho biến thể là bắt buộc.',
            'variants.*.attribute_value_ids.array'      => 'Các giá trị thuộc tính phải là một mảng.',
            'variants.*.attribute_value_ids.min'        => 'Mỗi biến thể phải có ít nhất một thuộc tính được chọn.',
            'variants.*.attribute_value_ids.*.required' => 'Giá trị thuộc tính là bắt buộc.',
            'variants.*.attribute_value_ids.*.exists'   => 'Giá trị thuộc tính đã chọn không tồn tại.',

            'new_images.array'                          => 'Ảnh sản phẩm mới phải là một mảng.',
            'new_images.*.required'                     => 'File ảnh mới là bắt buộc.',
            'new_images.*.file'                         => 'Phần tử ảnh phải là một file.',
            'new_images.*.mimetypes'                    => 'Định dạng file ảnh/video không hợp lệ (chỉ chấp nhận JPEG, PNG, MP4, WebM).',
            'new_images.*.max'                          => 'Kích thước file ảnh/video không được vượt quá 20MB.',

            'deleted_images.array'                      => 'Danh sách ảnh bị xóa phải là một mảng.',
            'deleted_images.*.integer'                  => 'ID ảnh bị xóa phải là số nguyên.',
            'deleted_images.*.exists'                   => 'ID ảnh bị xóa không tồn tại.',

            // Messages cho existing image IDs
            'variants.*.existing_image_ids.array'       => 'Danh sách ID ảnh hiện tại của biến thể phải là một mảng.',
            'variants.*.existing_image_ids.*.integer'   => 'ID ảnh hiện tại của biến thể phải là số nguyên.',
            'variants.*.existing_image_ids.*.exists'    => 'ID ảnh hiện tại không tồn tại hoặc không thuộc sản phẩm này.',

            // Messages cho new image indexes
            'variants.*.new_image_indexes.array'        => 'Danh sách chỉ số ảnh mới của biến thể phải là một mảng.',
            'variants.*.new_image_indexes.*.integer'    => 'Chỉ số ảnh mới của biến thể phải là số nguyên.',
            'variants.*.new_image_indexes.*.min'        => 'Chỉ số ảnh mới của biến thể phải lớn hơn hoặc bằng 0.',

            // Keep old messages for backward compatibility
            'variants.*.image_indexes.array'            => 'Chỉ số ảnh của biến thể phải là một mảng.',
            'variants.*.image_indexes.*.integer'        => 'Chỉ số ảnh của biến thể phải là số nguyên.',
        ];
    }
}
