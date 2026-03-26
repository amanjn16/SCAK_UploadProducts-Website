<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.sku' => ['nullable', 'string', 'max:255'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
            'products.*.status' => ['nullable', 'in:active,archived'],
            'products.*.description' => ['nullable', 'string'],
            'products.*.tags' => ['nullable', 'array'],
            'products.*.tags.*' => ['string', 'max:255'],
            'products.*.supplier' => ['nullable', 'string', 'max:255'],
            'products.*.city' => ['nullable', 'string', 'max:255'],
            'products.*.category' => ['nullable', 'string', 'max:255'],
            'products.*.top_fabric' => ['nullable', 'string', 'max:255'],
            'products.*.dupatta_fabric' => ['nullable', 'string', 'max:255'],
            'products.*.sizes' => ['nullable', 'array'],
            'products.*.sizes.*' => ['string', 'max:255'],
            'products.*.features' => ['nullable', 'array'],
            'products.*.features.*' => ['string', 'max:255'],
        ];
    }
}
