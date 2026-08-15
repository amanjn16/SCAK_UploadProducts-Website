@extends('layouts.app', ['title' => 'SCAK Admin Login', 'hideCustomerPrompt' => true])

@section('content')
    <section class="panel" style="max-width: 460px; margin: 30px auto; padding: 24px;">
        <h1 style="margin-top: 0;">Admin Login</h1>
        <p class="muted">Enter your approved phone number, six-digit PIN and current authenticator code.</p>

        <div class="field"><label for="adminPhone">Phone number</label><input id="adminPhone" inputmode="numeric" autocomplete="tel" placeholder="10-digit mobile number" maxlength="10"></div>
        <div class="field" style="margin-top: 14px;"><label for="adminPin">6-digit PIN</label><input id="adminPin" type="password" inputmode="numeric" autocomplete="current-password" placeholder="Enter PIN" maxlength="6"></div>
        <div class="field" style="margin-top: 14px;"><label for="adminCode">Authenticator code</label><input id="adminCode" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" maxlength="6"></div>
        <button class="btn-primary" id="adminLoginButton" type="button" style="width: 100%; margin-top: 16px;">Open Admin</button>

        <div id="authenticatorSetup" hidden style="margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--line);">
            <h2 style="font-size: 1.1rem;">One-time authenticator setup</h2>
            <p class="muted">In Google Authenticator or another authenticator, choose <strong>Enter a setup key</strong>. Use account name <strong>SCAK Admin</strong>, time-based key, and the key below.</p>
            <div class="field"><label>Setup key</label><input id="adminSetupKey" readonly></div>
            <div class="field" style="margin-top: 14px;"><label for="adminSetupCode">Code shown by authenticator</label><input id="adminSetupCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6"></div>
            <button class="btn-primary" id="confirmSetupButton" type="button" style="width: 100%; margin-top: 16px;">Confirm and Open Admin</button>
        </div>
        <div id="adminLoginStatus" class="muted" role="status" style="margin-top: 16px;"></div>
    </section>
@endsection

@push('scripts')
<script>
    const status = document.getElementById('adminLoginStatus');
    async function postAdminAuth(url, payload) {
        const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.scak.csrfToken }, body: JSON.stringify(payload) });
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || Object.values(body.errors || {})[0]?.[0] || 'Unable to continue.');
        return body;
    }
    document.getElementById('adminLoginButton').addEventListener('click', async () => {
        try {
            status.textContent = 'Checking securely...';
            const result = await postAdminAuth('{{ route('admin.auth.login') }}', { phone: document.getElementById('adminPhone').value, pin: document.getElementById('adminPin').value, code: document.getElementById('adminCode').value || null });
            if (result.setup_required) {
                document.getElementById('authenticatorSetup').hidden = false;
                document.getElementById('adminSetupKey').value = result.secret;
                status.textContent = result.message;
                document.getElementById('adminSetupCode').focus();
            } else window.location.assign(result.redirect);
        } catch (error) { status.textContent = error.message; }
    });
    document.getElementById('confirmSetupButton').addEventListener('click', async () => {
        try {
            status.textContent = 'Confirming authenticator...';
            const result = await postAdminAuth('{{ route('admin.auth.confirm-setup') }}', { code: document.getElementById('adminSetupCode').value });
            window.location.assign(result.redirect);
        } catch (error) { status.textContent = error.message; }
    });
</script>
@endpush
