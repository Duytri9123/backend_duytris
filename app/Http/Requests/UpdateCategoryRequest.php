<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Lấy category hiện tại từ route
        $category = $this->route('category');

        return [
            'name'      => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'img_url'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                'not_in:' . $category->id,
                function ($attribute, $value, $fail) use ($category) {
                    if ($value) {
                        $newParent = Category::with('ancestors')->find($value);
                        if (!$newParent) return;
                        if ($newParent->isDescendantOf($category)) {
                            $fail('Không thể chuyển danh mục cha thành danh mục con của chính nó.');
                        }
                        if ($newParent->getDepth() >= 2) {
                            $fail('Không thể tạo danh mục con quá 3 cấp.');
                        }
                    }
                },
            ],
        ];
    }
}
