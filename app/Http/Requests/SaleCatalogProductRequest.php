<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleCatalogProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_id' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,archived'],
            'images' => ['required', 'array', 'min:1', 'max:36'],
            'images.*.source_id' => ['required', 'string', 'max:100', 'distinct'],
            'images.*.url' => ['required', 'url', 'max:2048'],
            'images.*.is_cover' => ['sometimes', 'boolean'],
        ];
    }
}
