<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeValueRequest extends FormRequest
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
        $valueId = $this->route('value')->id;
        $attributeId = $this->route('value')->product_attribute_id;
       return [
            'value' => [
                'required', 'string', 'max:255',
                Rule::unique('attribute_values')
                    ->where('product_attribute_id', $attributeId)
                    ->ignore($valueId) // Bỏ qua chính nó khi kiểm tra unique
            ],
            'code'  => 'nullable|string|max:50',
        ];
    }
}
