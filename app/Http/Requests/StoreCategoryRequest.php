<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255|unique:categories,name',
            'img_url'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            // Thêm custom validation rule bằng Closure để kiểm tra độ sâu
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parentCategory = Category::find($value);

                        $parentCategory->load('ancestors');
                        if ($parentCategory->getDepth() >= 2) {
                            $fail('Không thể tạo danh mục con quá 3 cấp.');
                        }
                    }
                },
            ],
        ];
    }
}
