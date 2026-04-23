<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProductRequest extends FormRequest
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
        return [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'short_description' => 'nullable|string|max:400',
            'brand_id'      => 'required|exists:brands,id',
            'category_id'   => 'required|exists:categories,id',
            'status'        => ['required', new Enum(ProductStatus::class)],

            'variants'                       => 'required|array|min:1',
            // Dấu * có nghĩa là áp dụng rule cho mọi phần tử trong mảng 'variants'
            'variants.*.sku'                 => 'nullable|string|max:255|unique:product_variants,sku',
            'variants.*.selling_price'       => 'required|numeric|min:0',
            'variants.*.original_price'      => 'nullable|numeric|min:0|gte:variants.*.selling_price',
            'variants.*.quantity'            => 'required|integer|min:0',
            'variants.*.is_default'          => 'required|boolean',
            'variants.*.dimensions'     => 'nullable|string|max:255',
            'variants.*.weight'         => 'nullable|numeric|min:0',
            // Validate mảng các giá trị thuộc tính để định nghĩa biến thể
            'variants.*.attribute_value_ids' => 'required|array|min:1',
            'variants.*.attribute_value_ids.*' => 'required|exists:attribute_values,id',

            'images' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    $videoCount = 0;
                    foreach ($value as $file) {
                        if (str_starts_with($file->getMimeType(), 'video')) {
                            $videoCount++;
                        }
                    }
                    if ($videoCount > 1) { // Giới hạn chỉ 1 video
                        $fail('Chỉ được phép upload tối đa 1 video cho mỗi sản phẩm.');
                    }
                }
            ],
            'images.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,video/mp4,video/webm', 'max:20480'],
            'variants.*.image_indexes' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (!$value) return;

                    $imagesCount = count($this->file('images') ?? []);

                    foreach ($value as $index) {
                        if (!is_numeric($index) || $index < 0 || $index >= $imagesCount) {
                            $fail("Image index {$index} không hợp lệ. Chỉ số phải từ 0 đến " . ($imagesCount - 1));
                        }
                    }
                }
            ],
            'variants.*.image_indexes.*' => 'integer',
            'option_ids'   => 'nullable|array',
            'option_ids.*' => 'required|exists:attribute_values,id',
        ];
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
            'status.Illuminate\Validation\Rules\Enum'   => 'Trạng thái sản phẩm không hợp lệ.', // Tùy chỉnh nếu bạn muốn message cụ thể hơn cho Enum

            'variants.required'                         => 'Sản phẩm phải có ít nhất một biến thể.',
            'variants.array'                            => 'Biến thể phải là một mảng.',
            'variants.min'                              => 'Sản phẩm phải có ít nhất một biến thể.',

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

            // Messages cho attribute_value_ids lồng nhau
            'variants.*.attribute_value_ids.array'      => 'Các giá trị thuộc tính phải là một mảng.',
            'variants.*.attribute_value_ids.min'        => 'Mỗi biến thể phải có ít nhất một thuộc tính được chọn.', // Sửa message này cho hợp lý hơn
            'variants.*.attribute_value_ids.*.array'    => 'Mỗi nhóm giá trị thuộc tính phải là một mảng.',
            'variants.*.attribute_value_ids.*.min'      => 'Mỗi nhóm thuộc tính phải có ít nhất một giá trị được chọn.',
            'variants.*.attribute_value_ids.*.*.required' => 'Giá trị thuộc tính là bắt buộc.',
            'variants.*.attribute_value_ids.*.*.integer' => 'ID giá trị thuộc tính phải là số nguyên.',
            'variants.*.attribute_value_ids.*.*.exists' => 'Giá trị thuộc tính đã chọn không tồn tại.',

            'images.array'                              => 'Ảnh sản phẩm phải là một mảng.',
            'images.*.required'                         => 'File ảnh là bắt buộc.', // Nếu không có file mới nào
            'images.*.file'                             => 'Phần tử ảnh phải là một file.',
            'images.*.mimetypes'                        => 'Định dạng file ảnh/video không hợp lệ (chỉ chấp nhận JPEG, PNG, GIF, MP4, WebM).',
            'images.*.max'                              => 'Kích thước file ảnh/video không được vượt quá 20MB.',

            'deleted_images.array'                      => 'Danh sách ảnh bị xóa phải là một mảng.',
            'deleted_images.*.integer'                  => 'ID ảnh bị xóa phải là số nguyên.',
            'deleted_images.*.exists'                   => 'ID ảnh bị xóa không tồn tại.',

            'deleted_variant_ids.array'                 => 'Danh sách biến thể bị xóa phải là một mảng.',
            'deleted_variant_ids.*.integer'             => 'ID biến thể bị xóa phải là số nguyên.',
            'deleted_variant_ids.*.exists'              => 'ID biến thể bị xóa không tồn tại.',

            // Messages cho image_indexes của variants
            'variants.*.image_indexes.array'            => 'Chỉ số ảnh của biến thể phải là một mảng.',
            'variants.*.image_indexes.*.integer'        => 'Chỉ số ảnh của biến thể phải là số nguyên.',
            // Custom message cho lỗi index không hợp lệ được xử lý trong hàm closure
        ];
    }
}
