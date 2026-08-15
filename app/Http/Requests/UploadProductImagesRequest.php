<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.config('scak.images.max_per_product', 36)],
            'images.*' => ['required', 'image', 'max:10240'],
            'cover_index' => ['nullable', 'integer', 'min:0'],
            'watermarked' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');
            $existingCount = $product?->images()?->count() ?? 0;
            $incomingCount = count($this->file('images', []));
            $maxPerProduct = (int) config('scak.images.max_per_product', 36);

            if (($existingCount + $incomingCount) > $maxPerProduct) {
                $validator->errors()->add(
                    'images',
                    "A product can have at most {$maxPerProduct} images."
                );
            }
        });
    }
}
