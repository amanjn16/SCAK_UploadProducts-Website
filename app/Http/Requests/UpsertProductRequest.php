<?php

namespace App\Http\Requests;

use App\Models\ProductFieldSetting;
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
            'product_type' => ['required', 'in:regular,sale'],
            'brand' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0'],
            'brand_price' => ['nullable', 'numeric', 'gte:0'],
            'availability' => ['required', 'in:in_stock,out_of_stock'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'top_fabric' => ['nullable', 'string', 'max:255'],
            'dupatta_fabric' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:5000'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = (string) $this->input('product_type', 'regular');
            $requiredColumn = $type === 'sale' ? 'required_sale' : 'required_regular';
            $enabledColumn = $type === 'sale' ? 'enabled_sale' : 'enabled_regular';

            ProductFieldSetting::query()
                ->where($enabledColumn, true)
                ->where($requiredColumn, true)
                ->each(function ($setting) use ($validator): void {
                    $value = $this->input($setting->field_key);
                    if ($value === null || $value === '' || $value === []) {
                        $validator->errors()->add($setting->field_key, $setting->label.' is required for this product type.');
                    }
                });
        });
    }
}
