@guest
    <div id="customerOtpOverlay" class="drawer-overlay"></div>
    <div id="customerOtpModalShell" class="otp-modal-shell">
        <div id="customerOtpModal" class="panel" style="width:min(420px, calc(100vw - 24px)); max-width:100%; padding:22px;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="{{ $brandLogoUrl ?? (asset('assets/brand/scak-logo.png') . '?v=20260327d') }}" alt="SCAK" style="width:56px; height:56px; object-fit:contain;">
                    <div>
                        <strong>Stay Connected</strong>
                        <p class="muted" style="margin:6px 0 0;">Share your phone number if you would like us to contact you. This is optional.</p>
                    </div>
                </div>
                <button class="btn-secondary icon-btn" id="customerPhoneCloseButton" type="button" aria-label="Close" title="Close">&times;</button>
            </div>
            <div class="field" style="margin-top:18px;">
                <label for="customerPhoneInput">Phone number</label>
                <input id="customerPhoneInput" inputmode="tel" autocomplete="tel" placeholder="9997558700" maxlength="14">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:16px;">
                <button class="btn-primary" id="customerPhoneSubmitButton" type="button">Submit</button>
                <button class="btn-secondary" id="customerPhoneNotNowButton" type="button">Not now</button>
            </div>
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
            const dismissalKey = 'scak_phone_prompt_dismissed_at';

            function openPrompt() {
                overlay.classList.add('open');
                modalShell.classList.add('open');
                modal.style.display = 'block';
                window.setTimeout(() => phoneInput.focus(), 100);
            }

            function closePrompt(rememberDismissal = false) {
                overlay.classList.remove('open');
                modalShell.classList.remove('open');
                modal.style.display = 'none';

                if (rememberDismissal) {
                    localStorage.setItem(dismissalKey, String(Date.now()));
                }
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
                    localStorage.removeItem(dismissalKey);
                    window.setTimeout(() => closePrompt(false), 700);
                } catch (error) {
                    message.textContent = 'Unable to save the phone number. Please try again.';
                } finally {
                    submitButton.disabled = false;
                }
            }

            document.getElementById('customerPhoneCloseButton').addEventListener('click', () => closePrompt(true));
            document.getElementById('customerPhoneNotNowButton').addEventListener('click', () => closePrompt(true));
            submitButton.addEventListener('click', submitPhone);
            phoneInput.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitPhone();
                }
            });
            overlay.addEventListener('click', () => closePrompt(true));

            window.scakAuthPrompt = {
                open: openPrompt,
                close: closePrompt,
            };

            const dismissedAt = Number(localStorage.getItem(dismissalKey) || 0);
            const dismissalStillActive = dismissedAt > 0 && (Date.now() - dismissedAt) < (24 * 60 * 60 * 1000);
            if (!alreadySubmitted && !dismissalStillActive) {
                window.setTimeout(openPrompt, 15000);
            }
        })();
    </script>
    @endpush
@endguest
