@guest
    <div id="customerOtpOverlay" class="drawer-overlay"></div>
    <div id="customerOtpModal" class="panel" style="display:block; position:fixed; inset:auto 16px 16px 16px; z-index:30; max-width:460px; margin:0 auto; left:0; right:0; padding:22px;">
        <div id="customerOtpRequestStep">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
                <strong>Verify To Continue</strong>
            </div>
            <p class="muted" style="margin:10px 0 0;">Verify by OTP to browse the SCAK catalog and place your order.</p>
            <div class="field" style="margin-top:14px;">
                <label>Name</label>
                <input id="customerOtpName" placeholder="Enter your name">
            </div>
            <div class="field">
                <label>Phone</label>
                <input id="customerOtpPhone" placeholder="9876543210">
            </div>
            <div class="field">
                <label>City</label>
                <input id="customerOtpCity" placeholder="Hisar">
            </div>
            <button class="btn-primary" id="customerOtpRequestButton" style="width:100%; margin-top:16px;" type="button">Send WhatsApp OTP</button>
        </div>
        <div id="customerOtpVerifyStep" style="display:none;">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
                <strong>Enter OTP</strong>
                <button class="btn-secondary" id="customerOtpBack" type="button">Back</button>
            </div>
            <p class="muted" id="customerOtpSentTo" style="margin:10px 0 0;"></p>
            <div class="field" style="margin-top:14px;">
                <label>4-digit OTP</label>
                <input id="customerOtpCode" placeholder="1234" maxlength="4">
            </div>
            <button class="btn-primary" id="customerOtpVerifyButton" style="width:100%; margin-top:16px;" type="button">Verify OTP</button>
        </div>
        <p class="muted" id="customerOtpMessage" style="margin:14px 0 0;"></p>
    </div>

    @push('scripts')
    <script>
        (() => {
            const overlay = document.getElementById('customerOtpOverlay');
            const modal = document.getElementById('customerOtpModal');
            const requestStep = document.getElementById('customerOtpRequestStep');
            const verifyStep = document.getElementById('customerOtpVerifyStep');
            const message = document.getElementById('customerOtpMessage');
            const sentTo = document.getElementById('customerOtpSentTo');

            function showRequestStep() {
                requestStep.style.display = 'block';
                verifyStep.style.display = 'none';
                message.textContent = '';
            }

            function openPrompt() {
                document.body.classList.add('auth-locked');
                overlay.classList.add('open');
                modal.style.display = 'block';
            }

            function closePrompt() {
                document.body.classList.remove('auth-locked');
                overlay.classList.remove('open');
                modal.style.display = 'none';
            }

            async function requestOtp() {
                message.textContent = '';
                const response = await fetch('{{ route('customer.auth.request-otp') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.scak.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: document.getElementById('customerOtpName').value,
                        phone: document.getElementById('customerOtpPhone').value,
                        city: document.getElementById('customerOtpCity').value
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    message.textContent = data.message || 'Unable to send OTP.';
                    return;
                }

                requestStep.style.display = 'none';
                verifyStep.style.display = 'block';
                sentTo.textContent = `OTP sent to ${data.phone}${data.test_mode ? ' (test mode: check logs)' : ''}`;
            }

            async function verifyOtp() {
                message.textContent = '';
                const response = await fetch('{{ route('customer.auth.verify-otp') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.scak.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        phone: document.getElementById('customerOtpPhone').value,
                        code: document.getElementById('customerOtpCode').value
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    message.textContent = data.message || 'OTP verification failed.';
                    return;
                }

                window.location.reload();
            }

            document.getElementById('customerOtpRequestButton').addEventListener('click', requestOtp);
            document.getElementById('customerOtpVerifyButton').addEventListener('click', verifyOtp);
            document.getElementById('customerOtpBack').addEventListener('click', showRequestStep);

            window.scakAuthPrompt = {
                open: openPrompt,
                close: closePrompt,
                reset: showRequestStep,
            };

            openPrompt();
        })();
    </script>
    @endpush
@endguest
