@extends('layouts.app', ['title' => 'SCAK Login'])

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <span class="pill">OTP-protected access</span>
            <h1>Browse the new SCAK catalog with WhatsApp OTP.</h1>
            <p>Customers verify their phone number, explore the product collection, build a bucket, and send an order request directly to the admin team for offline follow-up.</p>
        </div>
        <div class="panel" style="padding: 24px;">
            <div id="stepRequestOtp">
                <h2 style="margin-top: 0;">Request OTP</h2>
                <div class="field">
                    <label>Name</label>
                    <input id="customerName" placeholder="Enter your name">
                </div>
                <div class="field">
                    <label>Phone</label>
                    <input id="customerPhone" placeholder="9876543210">
                </div>
                <div class="field">
                    <label>City</label>
                    <input id="customerCity" placeholder="Hisar">
                </div>
                <button class="btn-primary" id="requestOtpButton" style="width: 100%; margin-top: 16px;">Send WhatsApp OTP</button>
            </div>
            <div id="stepVerifyOtp" style="display: none;">
                <h2 style="margin-top: 0;">Verify OTP</h2>
                <p class="muted" id="otpSentTo"></p>
                <div class="field">
                    <label>4-digit OTP</label>
                    <input id="customerOtp" placeholder="1234" maxlength="4">
                </div>
                <button class="btn-primary" id="verifyOtpButton" style="width: 100%; margin-top: 16px;">Enter Catalog</button>
                <button class="btn-secondary" id="backToRequestButton" style="width: 100%; margin-top: 10px;">Change details</button>
            </div>
            <p class="muted" id="authMessage" style="margin-bottom: 0;"></p>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const requestStep = document.getElementById('stepRequestOtp');
    const verifyStep = document.getElementById('stepVerifyOtp');
    const authMessage = document.getElementById('authMessage');
    const otpSentTo = document.getElementById('otpSentTo');
    const requestOtpButton = document.getElementById('requestOtpButton');
    const verifyOtpButton = document.getElementById('verifyOtpButton');
    const backToRequestButton = document.getElementById('backToRequestButton');

    requestOtpButton.addEventListener('click', async () => {
        authMessage.textContent = '';
        const payload = {
            name: document.getElementById('customerName').value,
            phone: document.getElementById('customerPhone').value,
            city: document.getElementById('customerCity').value
        };

        const response = await fetch('{{ route('customer.auth.request-otp') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.scak.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok) {
            authMessage.textContent = data.message || 'Unable to send OTP.';
            return;
        }

        otpSentTo.textContent = `OTP sent to ${data.phone}${data.test_mode ? ' (test mode: check logs)' : ''}`;
        requestStep.style.display = 'none';
        verifyStep.style.display = 'block';
    });

    verifyOtpButton.addEventListener('click', async () => {
        authMessage.textContent = '';
        const response = await fetch('{{ route('customer.auth.verify-otp') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.scak.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                phone: document.getElementById('customerPhone').value,
                code: document.getElementById('customerOtp').value
            })
        });

        const data = await response.json();

        if (!response.ok) {
            authMessage.textContent = data.message || 'OTP verification failed.';
            return;
        }

        window.location.href = '{{ route('catalog') }}';
    });

    backToRequestButton.addEventListener('click', () => {
        requestStep.style.display = 'block';
        verifyStep.style.display = 'none';
        authMessage.textContent = '';
    });
</script>
@endpush
