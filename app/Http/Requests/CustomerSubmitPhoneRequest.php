<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerSubmitPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'digits:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.digits' => 'Please enter a 10-digit phone number.',
        ];
    }
}
