<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequestOtpRequest;
use App\Http\Requests\CustomerVerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function requestOtp(CustomerRequestOtpRequest $request): JsonResponse
    {
        $challenge = $this->otpService->issue('customer_login', $request->string('phone')->toString(), [
            'name' => $request->string('name')->toString(),
            'city' => $request->string('city')->toString(),
        ]);

        return response()->json([
            'message' => 'OTP sent successfully.',
            'phone' => $challenge->phone,
            'test_mode' => (bool) config('scak.otp.test_mode', true),
        ], 202);
    }

    public function verifyOtp(CustomerVerifyOtpRequest $request): JsonResponse
    {
        $challenge = $this->otpService->verify(
            'customer_login',
            $request->string('phone')->toString(),
            $request->string('code')->toString(),
        );

        $phone = PhoneNumber::normalizeIndian($request->string('phone')->toString());
        $existingUser = User::query()->where('phone', $phone)->first();

        $user = User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => data_get($challenge->meta, 'name', 'SCAK Customer'),
                'city' => data_get($challenge->meta, 'city'),
                'role' => $existingUser?->role ?: User::ROLE_CUSTOMER,
                'phone_verified_at' => now(),
                'last_login_at' => now(),
                'is_active' => true,
            ],
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Customer verified successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'city' => $user->city,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
