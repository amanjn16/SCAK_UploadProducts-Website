@guest
    <div id="customerOtpOverlay" class="drawer-overlay"></div>
    <div id="customerOtpModalShell" class="otp-modal-shell">
        <div id="customerOtpModal" class="panel" style="width:min(420px, calc(100vw - 24px)); max-width:100%; padding:22px;">
            <div style="display:flex; align-items:flex-start; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="{{ $brandLogoUrl ?? (asset('assets/brand/scak-logo.png') . '?v=20260327d') }}" alt="SCAK" style="width:56px; height:56px; object-fit:contain;">
                    <div>
                        <strong>Enter Your Phone Number</strong>
                        <p class="muted" style="margin:6px 0 0;">Enter your 10-digit phone number to continue. No OTP is required.</p>
                    </div>
                </div>
            </div>
            <div class="field" style="margin-top:18px;">
                <label for="customerPhoneInput">Phone number</label>
                <input id="customerPhoneInput" inputmode="numeric" pattern="[0-9]*" autocomplete="tel" placeholder="9997558700" maxlength="10">
            </div>
            <button class="btn-primary" id="customerPhoneSubmitButton" style="width:100%; margin-top:16px;" type="button">Submit</button>
            <p class="muted" id="customerOtpMessage" style="margin:14px 0 0;"></p>
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const overlay = document.getElementById('customerOtpOverlay');
            const modalShell = document.getElementById('customerOtpModalShell');
            const modal = document.getElementById('customerOtpModal');
            const phoneInput = document.getElementById('customerPhoneInput');
            const submitButton = document.getElementById('customerPhoneSubmitButton');
            const message = document.getElementById('customerOtpMessage');
            const alreadySubmitted = @json((bool) session('scak_customer_phone'));

            function openPrompt() {
                document.body.classList.add('auth-locked');
                overlay.classList.add('open');
                modalShell.classList.add('open');
                modal.style.display = 'block';
                window.setTimeout(() => phoneInput.focus(), 100);
            }

            function closePrompt() {
                document.body.classList.remove('auth-locked');
                overlay.classList.remove('open');
                modalShell.classList.remove('open');
                modal.style.display = 'none';
            }

            async function submitPhone() {
                message.textContent = '';
                submitButton.disabled = true;

                try {
                    const response = await fetch('{{ route('customer.auth.submit-phone') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.scak.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ phone: phoneInput.value })
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        const phoneError = data.errors?.phone?.[0];
                        message.textContent = phoneError || data.message || 'Unable to save the phone number.';
                        return;
                    }

                    message.textContent = data.message || 'Thank you.';
                    window.setTimeout(closePrompt, 500);
                } catch (error) {
                    message.textContent = 'Unable to save the phone number. Please try again.';
                } finally {
                    submitButton.disabled = false;
                }
            }

            submitButton.addEventListener('click', submitPhone);
            phoneInput.addEventListener('input', () => {
                phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
            });
            phoneInput.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitPhone();
                }
            });
            window.scakAuthPrompt = {
                open: openPrompt,
            };

            if (!alreadySubmitted) {
                openPrompt();
            }
        })();
    </script>
    @endpush
@endguest
