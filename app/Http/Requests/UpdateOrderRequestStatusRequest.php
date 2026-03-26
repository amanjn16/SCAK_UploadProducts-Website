<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:new,contacted,confirmed,paid_offline,dispatched,completed,cancelled'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
