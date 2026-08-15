<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequestOtpRequest;
use App\Http\Requests\AdminVerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function requestOtp(AdminRequestOtpRequest $request): JsonResponse
    {
        $phone = PhoneNumber::normalizeIndian($request->string('phone')->toString());
        $admin = User::query()
            ->where('phone', $phone)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->first();

        abort_unless($admin, 403, 'This phone number is not approved for admin access.');

        $challenge = $this->otpService->issue('admin_login', $phone);

        return response()->json([
            'message' => 'Admin OTP sent successfully.',
            'phone' => $challenge->phone,
            'test_mode' => (bool) config('scak.otp.test_mode', true),
        ], 202);
    }

    public function verifyOtp(AdminVerifyOtpRequest $request): JsonResponse
    {
        $phone = PhoneNumber::normalizeIndian($request->string('phone')->toString());
        $this->otpService->verify('admin_login', $phone, $request->string('code')->toString());

        $admin = User::query()
            ->where('phone', $phone)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->firstOrFail();
        $admin->forceFill([
            'phone_verified_at' => now(),
            'last_login_at' => now(),
            'approved_at' => $admin->approved_at ?? now(),
        ])->save();

        if ($request->filled('fcm_token')) {
            $admin->adminDevices()->updateOrCreate(
                ['fcm_token' => $request->string('fcm_token')->toString()],
                [
                    'device_name' => $request->string('device_name')->toString() ?: 'Android Device',
                    'platform' => 'android',
                    'last_seen_at' => now(),
                ],
            );
        }

        $token = $admin->createToken('android-admin')->plainTextToken;

        return response()->json([
            'message' => 'Admin authenticated successfully.',
            'token' => $token,
            'user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'phone' => $admin->phone,
                'city' => $admin->city,
                'role' => $admin->role,
            ],
        ]);
    }
}
