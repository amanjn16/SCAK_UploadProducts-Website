<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TotpServiceTest extends TestCase
{
    public function test_generated_secret_produces_a_valid_current_six_digit_code(): void
    {
        $service = new TotpService;
        $secret = $service->generateSecret();
        $method = new ReflectionMethod($service, 'code');
        $code = $method->invoke($service, $secret, intdiv(time(), 30));

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($service->verify($secret, $code));
        $this->assertFalse($service->verify($secret, '123'));
        $this->assertStringStartsWith('otpauth://totp/', $service->provisioningUri($secret, '+919000000000'));
    }
}
