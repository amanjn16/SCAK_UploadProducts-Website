<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminAuthController extends Controller
{
    public function __construct(private readonly TotpService $totp) {}

    public function show(): View|RedirectResponse
    {
        return Auth::user()?->isAdmin()
            ? redirect()->route('admin.dashboard')
            : view('auth.admin-login');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:6'],
            'code' => ['nullable', 'digits:6'],
        ]);

        try {
            $phone = PhoneNumber::normalizeIndian($data['phone']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        $admin = User::query()->where('phone', $phone)->first();
        $valid = $admin?->isAdmin() && $admin->is_active && filled($admin->password)
            && Hash::check($data['pin'], $admin->password);
        if (! $valid) {
            throw ValidationException::withMessages(['phone' => 'The phone number or PIN is incorrect.']);
        }

        if (! $admin->totp_confirmed_at || ! filled($admin->totp_secret)) {
            $secret = $this->totp->generateSecret();
            $request->session()->put('admin_totp_setup', [
                'user_id' => $admin->id,
                'secret' => $secret,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return response()->json([
                'setup_required' => true,
                'secret' => $secret,
                'provisioning_uri' => $this->totp->provisioningUri($secret, $admin->phone),
                'message' => 'Add this setup key to your authenticator, then enter its six-digit code.',
            ]);
        }

        if (! filled($data['code']) || ! $this->totp->verify($admin->totp_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is incorrect or expired.']);
        }

        return $this->finishLogin($request, $admin);
    }

    public function confirmSetup(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $setup = $request->session()->get('admin_totp_setup');
        abort_unless(is_array($setup) && ($setup['expires_at'] ?? 0) >= time(), 419, 'Authenticator setup expired. Start again.');

        $admin = User::query()->findOrFail($setup['user_id']);
        abort_unless($admin->isAdmin() && $admin->is_active, 403);
        if (! $this->totp->verify($setup['secret'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is incorrect or expired.']);
        }

        $admin->forceFill(['totp_secret' => $setup['secret'], 'totp_confirmed_at' => now()])->save();
        $request->session()->forget('admin_totp_setup');

        return $this->finishLogin($request, $admin);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function finishLogin(Request $request, User $admin): JsonResponse
    {
        $admin->forceFill(['last_login_at' => now(), 'approved_at' => $admin->approved_at ?? now()])->save();
        Auth::login($admin);
        $request->session()->regenerate();

        return response()->json(['message' => 'Admin authenticated.', 'redirect' => route('admin.dashboard')]);
    }
}
