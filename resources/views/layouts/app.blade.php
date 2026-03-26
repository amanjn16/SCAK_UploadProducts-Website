<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SCAK' }}</title>
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
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(197,146,49,0.18), transparent 28%),
                linear-gradient(180deg, #f8f5ef 0%, #efe6d8 100%);
            min-height: 100vh;
        }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 24px;
            background: rgba(255,255,255,0.72);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(31,42,55,0.08);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .brand strong {
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .brand span {
            color: #6d5842;
            font-size: 0.92rem;
        }
        .nav {
            display: flex;
            gap: 16px;
            align-items: center;
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
            bottom: 88px;
            width: 56px;
            height: 56px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 14px 30px rgba(31, 42, 55, 0.18);
            z-index: 16;
        }
        .cart-chip {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 15;
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
            padding: 20px 22px 28px;
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
            margin: 4px 0 10px;
            color: #6d5842;
            font-size: 0.95rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #6d5842;
        }
        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; }
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }
            .nav {
                justify-content: space-between;
                flex-wrap: wrap;
            }
        }
        @media (max-width: 640px) {
            .shell {
                padding: 16px;
            }
            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            .product-card-body {
                padding: 12px;
                gap: 8px;
            }
            .floating-filter-btn,
            .cart-chip {
                right: 16px;
            }
        }
    </style>
    @stack('head')
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('catalog') : route('login') }}">
            <strong>SCAK</strong>
            <span>Wholesale catalog and order requests</span>
        </a>
        <nav class="nav">
            @auth
                <a href="{{ route('catalog') }}">Catalog</a>
                <a href="{{ route('bucket') }}">Cart</a>
                <span class="pill">{{ auth()->user()->name }} / {{ auth()->user()->phone }}</span>
                <button class="btn-secondary" id="logoutButton" type="button">Logout</button>
            @else
                <a href="{{ route('login') }}">Login</a>
            @endauth
        </nav>
    </header>
    <main class="shell">
        @yield('content')
    </main>
    <script>
        window.scak = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').content,
            cartKey: 'scak_cart_v2'
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
    </script>
    @stack('scripts')
</body>
</html>
