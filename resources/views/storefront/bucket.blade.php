@extends('layouts.app', ['title' => 'Your Cart'])

@section('content')
    <div class="panel" style="padding: 24px;">
        <h1 style="margin-top: 0;">Your Cart</h1>
        <p class="muted">Review the products you want, add an optional note, and place your order. The SCAK admin team will contact you to confirm payment and dispatch offline.</p>
        <style>
            .cart-items {
                display: grid;
                gap: 14px;
                margin-top: 18px;
            }
            .cart-item {
                display: grid;
                grid-template-columns: 108px minmax(0, 1fr);
                gap: 12px;
                align-items: stretch;
            }
            .cart-item img {
                width: 100%;
                height: 100%;
                min-height: 108px;
                object-fit: cover;
                border-radius: 18px;
            }
            .cart-item-body {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .cart-item-qty {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cart-qty-button {
                min-width: 36px;
                height: 36px;
                padding: 0;
            }
            .cart-delete-button {
                min-width: 40px;
                height: 36px;
                padding: 0 10px;
                font-size: 1rem;
            }
            .cart-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-top: 18px;
                max-width: 360px;
            }
            .cart-actions .btn-primary,
            .cart-actions .btn-secondary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
            }
            .cart-help {
                margin-top: 16px;
            }
            .order-success-overlay {
                position: fixed;
                inset: 0;
                background: rgba(31, 42, 55, 0.45);
                backdrop-filter: blur(8px);
                display: none;
                align-items: center;
                justify-content: center;
                padding: 16px;
                z-index: 40;
            }
            .order-success-modal {
                width: min(420px, 100%);
                padding: 24px;
            }
            .order-success-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-top: 18px;
            }
            @media (max-width: 640px) {
                .cart-item {
                    grid-template-columns: 88px minmax(0, 1fr);
                    gap: 10px;
                }
                .cart-item img {
                    min-height: 88px;
                    border-radius: 14px;
                }
                .cart-actions {
                    max-width: none;
                }
            }
        </style>
        <div id="bucketItems" class="cart-items"></div>
        <div id="bucketEmpty" class="empty-state" style="display: none;">Your cart is empty. Go back to the catalog and add some products.</div>
        <div class="field" style="margin-top: 20px;">
            <label>Optional note</label>
            <textarea id="bucketNote" rows="4" placeholder="Mention quantity preferences, timing, or any special request."></textarea>
        </div>
        <div class="cart-actions">
            <button class="btn-primary" id="submitBucketButton">Place Order</button>
            <a class="btn-secondary" href="{{ route('catalog') }}">Back to Catalog</a>
        </div>
        <p class="muted" id="bucketMessage"></p>
        <p class="muted cart-help">In case of any queries call / WhatsApp 9350188297.</p>
    </div>
    <div id="orderSuccessOverlay" class="order-success-overlay">
        <div class="panel order-success-modal">
            <h2 style="margin-top:0;">Order Placed</h2>
            <p style="margin:0;">We will get back to you soon.</p>
            <p class="muted" style="margin:10px 0 0;">For any queries call us on 9350188297.</p>
            <p class="muted" id="orderSuccessReference" style="margin:10px 0 0;"></p>
            <div class="order-success-actions">
                <a class="btn-secondary" href="{{ route('catalog') }}">Back to Catalog</a>
                <button class="btn-primary" id="closeOrderSuccessButton" type="button">Close</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const bucketItems = document.getElementById('bucketItems');
    const bucketEmpty = document.getElementById('bucketEmpty');
    const bucketMessage = document.getElementById('bucketMessage');
    const submitButton = document.getElementById('submitBucketButton');
    const orderSuccessOverlay = document.getElementById('orderSuccessOverlay');
    const orderSuccessReference = document.getElementById('orderSuccessReference');
    const closeOrderSuccessButton = document.getElementById('closeOrderSuccessButton');

    function showOrderSuccess(referenceCode) {
        orderSuccessReference.textContent = referenceCode ? `Reference code: ${referenceCode}` : '';
        orderSuccessOverlay.style.display = 'flex';
    }

    async function renderBucket(showEmptyState = true) {
        const cart = window.scakCart.get();
        const ids = Object.keys(cart);

        if (ids.length === 0) {
            bucketItems.innerHTML = '';
            bucketEmpty.style.display = showEmptyState ? 'block' : 'none';
            submitButton.disabled = true;
            return;
        }

        bucketEmpty.style.display = 'none';
        submitButton.disabled = false;

        const response = await fetch(`/products?ids=${ids.join(',')}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();

        bucketItems.innerHTML = data.data.map(product => `
            <article class="cart-item">
                <img src="${product.cover_image_url || 'https://placehold.co/600x750?text=SCAK'}" alt="${product.name}">
                <div class="cart-item-body">
                    <strong>${product.name}</strong>
                    <div>Rs. ${Number(product.price).toFixed(2)} x ${cart[product.id]}</div>
                    <div class="cart-item-qty">
                        <button class="btn-secondary cart-qty-button" onclick="updateQuantity(${product.id}, -1)">-</button>
                        <span>${cart[product.id]}</span>
                        <button class="btn-secondary cart-qty-button" onclick="updateQuantity(${product.id}, 1)">+</button>
                        <button class="btn-secondary cart-delete-button" aria-label="Remove item" onclick="removeFromBucket(${product.id})">&#128465;</button>
                    </div>
                </div>
            </article>
        `).join('');
    }

    function updateQuantity(productId, delta) {
        const cart = window.scakCart.get();
        const nextQuantity = (cart[productId] || 0) + delta;

        if (nextQuantity <= 0) {
            delete cart[productId];
        } else {
            cart[productId] = nextQuantity;
        }

        window.scakCart.save(cart);
        renderBucket();
    }

    function removeFromBucket(productId) {
        window.scakCart.remove(productId);
        renderBucket();
    }

    submitButton.addEventListener('click', async () => {
        const cart = window.scakCart.get();
        bucketMessage.textContent = '';

        if (Object.keys(cart).length === 0) {
            bucketEmpty.style.display = 'block';
            bucketMessage.textContent = '';
            return;
        }

        const payload = {
            note: document.getElementById('bucketNote').value,
            items: Object.entries(cart).map(([product_id, quantity]) => ({
                product_id: Number(product_id),
                quantity: Number(quantity)
            }))
        };

        const response = await fetch('{{ route('order-requests.store') }}', {
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
            if (response.status === 401) {
                bucketMessage.textContent = 'Verify OTP to place your order.';
                window.scakAuthPrompt?.open();
                return;
            }
            bucketMessage.textContent = data.message || 'Unable to place your order.';
            return;
        }

        window.scakCart.clear();
        await renderBucket(false);
        bucketMessage.textContent = '';
        showOrderSuccess(data.reference_code);
    });

    closeOrderSuccessButton.addEventListener('click', () => {
        orderSuccessOverlay.style.display = 'none';
    });

    renderBucket();
</script>
@endpush
