<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via Policy in controller
    }

    public function rules(): array
    {
        return [
            'category_id'  => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'name'         => ['sometimes', 'string', 'max:255'],
            'sku'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'cost_price'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock'        => ['sometimes', 'integer', 'min:0'],
            'min_stock'    => ['sometimes', 'nullable', 'integer', 'min:0'],
            'image'        => ['sometimes', 'nullable', function ($attribute, $value, $fail) {
                if (!is_string($value) && !($value instanceof \Illuminate\Http\UploadedFile)) {
                    $fail('The '.$attribute.' must be an image file or string.');
                }
            }],
            'remove_image' => ['sometimes', 'nullable', 'string'],
            'is_active'    => ['sometimes', 'nullable', 'boolean'],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['uuid', 'exists:modifier_groups,id'],
            'clear_modifier_groups' => ['nullable', 'string'],
        ];
    }
}
