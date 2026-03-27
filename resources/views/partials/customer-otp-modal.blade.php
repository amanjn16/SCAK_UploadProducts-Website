@guest
    <div id="customerOtpOverlay" class="drawer-overlay"></div>
    <div id="customerOtpModalShell" class="otp-modal-shell open">
        <div id="customerOtpModal" class="panel" style="width:min(420px, calc(100vw - 24px)); max-width:100%; max-height:min(84vh, 720px); overflow-y:auto; padding:22px;">
            <div style="display:flex; align-items:center; gap:12px;">
            <img src="{{ $brandLogoUrl ?? (asset('assets/brand/scak-logo.png') . '?v=20260327c') }}" alt="SCAK" style="width:56px; height:56px; object-fit:contain;">
                <div>
                    <strong>Verify To Continue</strong>
                    <p class="muted" style="margin:6px 0 0;">Verify by OTP to browse the SCAK catalog and place your order.</p>
                </div>
            </div>
            <div id="customerOtpRequestStep">
                @include('partials.customer-contact-fields', [
                    'nameId' => 'customerOtpName',
                    'phoneId' => 'customerOtpPhone',
                    'cityId' => 'customerOtpCity',
                    'withTopMargin' => true,
                ])
                <button class="btn-primary" id="customerOtpRequestButton" style="width:100%; margin-top:16px;" type="button">Send WhatsApp OTP</button>
                @include('partials.customer-support-note')
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
                @include('partials.customer-support-note')
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
                modalShell.classList.add('open');
                modal.style.display = 'block';
            }

            function closePrompt() {
                document.body.classList.remove('auth-locked');
                overlay.classList.remove('open');
                modalShell.classList.remove('open');
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
