<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerSubmitPhoneRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CustomerAuthController extends Controller
{
    public function submitPhone(CustomerSubmitPhoneRequest $request): JsonResponse
    {
        try {
            $phone = PhoneNumber::normalizeIndian($request->string('phone')->toString());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'phone' => $exception->getMessage(),
            ]);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'SCAK Customer',
                'role' => User::ROLE_CUSTOMER,
                'is_active' => true,
            ],
        );

        $request->session()->put('scak_customer_phone', $user->phone);

        return response()->json([
            'message' => 'Thank you. You can continue browsing.',
            'phone' => $user->phone,
        ]);
    }

}
