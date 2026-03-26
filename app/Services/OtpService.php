<?php

namespace App\Services;

use App\Models\OtpChallenge;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function issue(string $purpose, string $phone, array $meta = []): OtpChallenge
    {
        $normalizedPhone = PhoneNumber::normalizeIndian($phone);
        $cooldown = (int) config('scak.otp.resend_cooldown_seconds', 120);

        $recentChallenge = OtpChallenge::query()
            ->where('purpose', $purpose)
            ->where('phone', $normalizedPhone)
            ->where('created_at', '>=', now()->subSeconds($cooldown))
            ->latest()
            ->first();

        abort_if($recentChallenge !== null, 429, 'Please wait before requesting another OTP.');

        $challenge = OtpChallenge::query()->create([
            'purpose' => $purpose,
            'phone' => $normalizedPhone,
            'code' => (string) random_int(1000, 9999),
            'max_attempts' => (int) config('scak.otp.max_attempts', 5),
            'meta' => $meta,
            'expires_at' => now()->addMinutes((int) config('scak.otp.expires_in_minutes', 10)),
            'last_sent_at' => now(),
            'channel' => 'whatsapp',
        ]);

        $this->dispatchOtp($challenge);

        return $challenge;
    }

    public function verify(string $purpose, string $phone, string $code): OtpChallenge
    {
        $normalizedPhone = PhoneNumber::normalizeIndian($phone);

        $challenge = OtpChallenge::query()
            ->where('purpose', $purpose)
            ->where('phone', $normalizedPhone)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        abort_unless($challenge, 422, 'No OTP request found for this phone number.');
        abort_if($challenge->expires_at->isPast(), 422, 'OTP has expired.');
        abort_if($challenge->attempts >= $challenge->max_attempts, 429, 'Maximum OTP attempts reached.');

        if ($challenge->code !== trim($code)) {
            $challenge->increment('attempts');
            abort(422, 'Invalid OTP.');
        }

        $challenge->forceFill([
            'verified_at' => now(),
            'attempts' => $challenge->attempts + 1,
        ])->save();

        return $challenge->refresh();
    }

    protected function dispatchOtp(OtpChallenge $challenge): void
    {
        if ((bool) config('scak.otp.test_mode', true) || blank(config('scak.otp.endpoint'))) {
            Log::info('SCAK OTP generated in test mode.', [
                'purpose' => $challenge->purpose,
                'phone' => $challenge->phone,
                'code' => $challenge->code,
            ]);

            return;
        }

        try {
            Http::timeout((int) config('scak.otp.timeout', 15))
                ->post(config('scak.otp.endpoint'), [
                    'event' => 'OTP_verification',
                    'purpose' => $challenge->purpose,
                    'phone' => $challenge->phone,
                    'otp' => $challenge->code,
                    'meta' => $challenge->meta ?? [],
                    'expires_at' => Carbon::parse($challenge->expires_at)->toIso8601String(),
                ])
                ->throw();
        } catch (RequestException $exception) {
            Log::error('Failed to dispatch SCAK OTP.', [
                'phone' => $challenge->phone,
                'purpose' => $challenge->purpose,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
