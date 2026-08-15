<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductFieldSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFieldSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ProductFieldSetting::query()->orderBy('sort_order')->get()]);
    }

    public function update(Request $request, ProductFieldSetting $productFieldSetting): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'enabled_regular' => ['sometimes', 'boolean'], 'enabled_sale' => ['sometimes', 'boolean'],
            'required_regular' => ['sometimes', 'boolean'], 'required_sale' => ['sometimes', 'boolean'],
            'show_customer' => ['sometimes', 'boolean'], 'show_exports' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        if (array_key_exists('enabled_regular', $data) && ! $data['enabled_regular']) {
            $data['required_regular'] = false;
        }
        if (array_key_exists('enabled_sale', $data) && ! $data['enabled_sale']) {
            $data['required_sale'] = false;
        }
        $productFieldSetting->update($data);

        return response()->json(['message' => 'Field setting updated.', 'data' => $productFieldSetting->fresh()]);
    }
}
