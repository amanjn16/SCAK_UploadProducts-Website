<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $brandAssetVersion = '20260327c';
        $brandLogoUrl = asset('assets/brand/scak-logo.png') . '?v=' . $brandAssetVersion;
        $brandFilterUrl = asset('assets/brand/filter.png') . '?v=' . $brandAssetVersion;
        $brandCartUrl = asset('assets/brand/cart.png') . '?v=' . $brandAssetVersion;
        $brandWhatsappUrl = asset('assets/brand/whatsapp.png') . '?v=' . $brandAssetVersion;
    @endphp
    <title>{{ $title ?? 'SCAK' }}</title>
    <link rel="icon" type="image/png" href="{{ $brandLogoUrl }}">
    <style>
        :root {
            --sand: #f4efe7;
            --ink: #1f2a37;
            --accent: #9f3a22;
            --accent-dark: #7b2b18;
            --gold: #c59231;
            --card: #fffdfa;
            --line: #e4d9cb;
        }
        * { box-sizing: border-box; }
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(197,146,49,0.18), transparent 28%),
                linear-gradient(180deg, #f8f5ef 0%, #efe6d8 100%);
            min-height: 100vh;
        }
        body.auth-locked {
            overflow: hidden;
        }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 112px 24px 24px;
        }
        .announcement-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 28px;
            background: #2f241d;
            color: #f7efe2;
            z-index: 22;
            overflow: hidden;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .announcement-track {
            display: inline-flex;
            align-items: center;
            gap: 28px;
            white-space: nowrap;
            padding-left: 100%;
            animation: scak-marquee 26s linear infinite;
            will-change: transform;
        }
        .announcement-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            letter-spacing: 0.03em;
        }
        .announcement-item::after {
            content: "•";
            opacity: 0.7;
        }
        .announcement-item:last-child::after {
            content: "";
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(31,42,55,0.08);
            position: fixed;
            top: 36px;
            left: 50%;
            width: min(1180px, calc(100vw - 24px));
            transform: translate(-50%, -120%);
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(31, 42, 55, 0.12);
            z-index: 20;
            transition: transform .22s ease;
        }
        .topbar.visible {
            transform: translate(-50%, 0);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0;
            min-width: 0;
        }
        .brand img {
            width: 124px;
            height: 48px;
            object-fit: contain;
            flex: 0 0 auto;
        }
        .brand-copy {
            display: none;
        }
        .brand-name {
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-size: 0.8rem;
            line-height: 1;
        }
        .brand-tagline {
            color: #6d5842;
            font-size: 0.74rem;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nav {
            display: flex;
            gap: 8px;
            align-items: center;
            flex: 0 0 auto;
        }
        .btn, button {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            cursor: pointer;
            transition: transform .18s ease, background .18s ease;
        }
        .btn:hover, button:hover { transform: translateY(-1px); }
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        .btn-primary:hover { background: var(--accent-dark); }
        .btn-secondary {
            background: white;
            border: 1px solid var(--line);
            color: var(--ink);
        }
        .icon-btn {
            width: 42px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .icon-btn svg {
            width: 18px;
            height: 18px;
        }
        .panel {
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(228,217,203,0.85);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(104, 82, 58, 0.08);
        }
        .grid {
            display: grid;
            gap: 20px;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 22px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .product-card img {
            width: 100%;
            aspect-ratio: 4 / 5;
            object-fit: cover;
            background: #f3ebe0;
        }
        .product-card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .product-card-title {
            font-size: 0.84rem;
            line-height: 1.2;
        }
        .muted { color: #6d5842; }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #f4ece0;
            color: #6d5842;
            font-size: 0.86rem;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .field input, .field select, .field textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 14px;
            background: white;
        }
        .hero {
            display: grid;
            gap: 20px;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
        }
        .hero-copy h1 {
            font-size: clamp(2.1rem, 4vw, 4rem);
            margin: 0 0 16px;
            line-height: 0.95;
        }
        .hero-copy p {
            font-size: 1.05rem;
            max-width: 42rem;
            color: #5f5144;
        }
        .floating-filter-btn {
            position: fixed;
            right: 24px;
            bottom: 160px;
            width: 60px;
            height: 60px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 14px 30px rgba(31, 42, 55, 0.18);
            z-index: 16;
        }
        .floating-filter-btn img,
        .cart-chip img,
        .whatsapp-chip img {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }
        .cart-chip {
            position: fixed;
            right: 24px;
            bottom: 88px;
            z-index: 15;
            min-width: 60px;
            height: 60px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 14px 30px rgba(31, 42, 55, 0.18);
        }
        .whatsapp-chip {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 15;
            width: 60px;
            height: 60px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 14px 30px rgba(31, 42, 55, 0.18);
        }
        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(31, 42, 55, 0.35);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
            z-index: 24;
        }
        .drawer-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
        .drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: min(420px, 92vw);
            height: 100vh;
            background: rgba(255,255,255,0.98);
            border-left: 1px solid var(--line);
            box-shadow: -20px 0 40px rgba(31, 42, 55, 0.12);
            transform: translateX(100%);
            transition: transform .2s ease;
            z-index: 25;
            display: flex;
            flex-direction: column;
        }
        .drawer.open {
            transform: translateX(0);
        }
        .drawer-header {
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--line);
        }
        .drawer-body {
            padding: 20px 22px calc(118px + env(safe-area-inset-bottom, 0px));
            overflow-y: auto;
            display: grid;
            gap: 18px;
        }
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tile-date {
            margin: 2px 0 12px;
            padding: 10px 14px;
            border-radius: 14px;
            background: #2f241d;
            color: #f7efe2;
            font-size: 0.9rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #6d5842;
        }
        body.auth-locked .topbar,
        body.auth-locked .announcement-bar,
        body.auth-locked .shell {
            filter: blur(12px);
            pointer-events: none;
            user-select: none;
        }
        @keyframes scak-marquee {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-100%, 0, 0); }
        }
        .otp-modal-shell {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 14px;
            width: 100vw;
            min-height: 100dvh;
            overflow-y: auto;
            z-index: 30;
        }
        .otp-modal-shell.open {
            display: flex;
        }
        .otp-modal-shell .panel {
            margin: auto;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }
        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; }
            .topbar {
                top: 28px;
                width: calc(100vw - 16px);
                border-radius: 0 0 22px 22px;
                gap: 8px;
                padding: 10px 12px;
            }
            .nav {
                margin-left: auto;
            }
            .brand img {
                width: 108px;
                height: 42px;
            }
            .shell {
                padding-top: 100px;
            }
            .brand-tagline {
                display: none;
            }
        }
        @media (max-width: 640px) {
            .shell {
                padding: 106px 16px 16px;
            }
            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            .product-card-body {
                padding: 12px;
                gap: 8px;
            }
            .product-card-title {
                font-size: 0.78rem;
            }
            .topbar {
                width: calc(100vw - 10px);
                padding: 8px 10px;
            }
            .floating-filter-btn,
            .cart-chip,
            .whatsapp-chip {
                right: 16px;
            }
        }
    </style>
    @stack('head')
</head>
<body class="@guest auth-locked @endguest">
    @if(!empty($storefrontGroupLinks))
        <div class="announcement-bar" aria-label="Join our groups">
            <div class="announcement-track">
                @foreach($storefrontGroupLinks as $groupLink)
                    @if(!empty($groupLink['url']))
                        <a class="announcement-item" href="{{ $groupLink['url'] }}" target="_blank" rel="noopener">
                            Join our groups - {{ $groupLink['label'] }}
                        </a>
                    @else
                        <span class="announcement-item">Join our groups - {{ $groupLink['label'] }}</span>
                    @endif
                @endforeach
                @foreach($storefrontGroupLinks as $groupLink)
                    @if(!empty($groupLink['url']))
                        <a class="announcement-item" href="{{ $groupLink['url'] }}" target="_blank" rel="noopener">
                            Join our groups - {{ $groupLink['label'] }}
                        </a>
                    @else
                        <span class="announcement-item">Join our groups - {{ $groupLink['label'] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('catalog') : route('login') }}">
            <img src="{{ $brandLogoUrl }}" alt="SCAK">
        </a>
        <nav class="nav">
            @auth
                <button class="btn-secondary icon-btn" id="logoutButton" type="button" aria-label="Logout" title="Logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>
                    </svg>
                </button>
            @else
                <button class="btn-secondary icon-btn" type="button" aria-label="Verify by OTP" title="Verify by OTP" onclick="window.scakAuthPrompt?.open()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 4h16v16H4z"/>
                        <path d="M8 10h8"/>
                        <path d="M8 14h5"/>
                    </svg>
                </button>
            @endauth
        </nav>
    </header>
    <main class="shell">
        @yield('content')
    </main>
    @include('partials.customer-otp-modal')
    <script>
        window.scak = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').content,
            cartKey: 'scak_cart_v2'
        };
        window.scakCatalogState = {
            key: 'scak_catalog_state_v1',
            save(state) {
                try {
                    sessionStorage.setItem(this.key, JSON.stringify(state));
                } catch (error) {}
            },
            read() {
                try {
                    return JSON.parse(sessionStorage.getItem(this.key) || 'null');
                } catch (error) {
                    return null;
                }
            },
            clear() {
                try {
                    sessionStorage.removeItem(this.key);
                } catch (error) {}
            },
            goBack(fallbackUrl) {
                window.location.href = fallbackUrl;
            }
        };

        window.scakCart = {
            get() {
                try {
                    return JSON.parse(localStorage.getItem(window.scak.cartKey) || '{}');
                } catch (error) {
                    return {};
                }
            },
            save(data) {
                localStorage.setItem(window.scak.cartKey, JSON.stringify(data));
            },
            add(productId, quantity = 1) {
                const cart = this.get();
                cart[productId] = (cart[productId] || 0) + quantity;
                this.save(cart);
                return cart;
            },
            remove(productId) {
                const cart = this.get();
                delete cart[productId];
                this.save(cart);
                return cart;
            },
            clear() {
                localStorage.removeItem(window.scak.cartKey);
            },
            count() {
                return Object.values(this.get()).reduce((sum, value) => sum + Number(value), 0);
            }
        };

        const logoutButton = document.getElementById('logoutButton');
        if (logoutButton) {
            logoutButton.addEventListener('click', async () => {
                await fetch('{{ route('auth.logout') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.scak.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                window.location.href = '{{ route('login') }}';
            });
        }

        const topbar = document.querySelector('.topbar');
        let lastScrollY = window.scrollY;

        function syncTopbarVisibility() {
            const currentScrollY = window.scrollY;
            const show = currentScrollY < 40 || currentScrollY < lastScrollY;
            topbar?.classList.toggle('visible', show);
            lastScrollY = currentScrollY;
        }

        syncTopbarVisibility();
        window.addEventListener('scroll', syncTopbarVisibility, { passive: true });
    </script>
    @stack('scripts')
</body>
</html>
