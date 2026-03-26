<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminUserUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:admin,super_admin'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
