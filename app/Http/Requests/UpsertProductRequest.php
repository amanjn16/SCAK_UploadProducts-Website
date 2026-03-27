<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'top_fabric' => ['nullable', 'string', 'max:255'],
            'dupatta_fabric' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,archived'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:255'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'cover_image_id' => ['nullable', 'integer', 'exists:product_images,id'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['integer', 'exists:product_images,id'],
        ];
    }
}
