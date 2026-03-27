@extends('layouts.app', ['title' => 'Your Cart'])

@section('content')
    <div class="panel" style="padding: 24px;">
        <h1 style="margin-top: 0;">Your Cart</h1>
        <p class="muted">Review the products you want, add an optional note, and place your order. The SCAK admin team will contact you to confirm payment and dispatch offline.</p>
        <div id="bucketItems" class="grid" style="margin-top: 18px;"></div>
        <div id="bucketEmpty" class="empty-state" style="display: none;">Your cart is empty. Go back to the catalog and add some products.</div>
        <div class="field" style="margin-top: 20px;">
            <label>Optional note</label>
            <textarea id="bucketNote" rows="4" placeholder="Mention quantity preferences, timing, or any special request."></textarea>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px;">
            <button class="btn-primary" id="submitBucketButton">Place Order</button>
            <a class="btn-secondary" href="{{ route('catalog') }}">Back to Catalog</a>
        </div>
        <p class="muted" id="bucketMessage"></p>
    </div>
@endsection

@push('scripts')
<script>
    const bucketItems = document.getElementById('bucketItems');
    const bucketEmpty = document.getElementById('bucketEmpty');
    const bucketMessage = document.getElementById('bucketMessage');
    const submitButton = document.getElementById('submitBucketButton');

    async function renderBucket() {
        const cart = window.scakCart.get();
        const ids = Object.keys(cart);

        if (ids.length === 0) {
            bucketItems.innerHTML = '';
            bucketEmpty.style.display = 'block';
            submitButton.disabled = true;
            return;
        }

        bucketEmpty.style.display = 'none';
        submitButton.disabled = false;

        const response = await fetch(`/products?ids=${ids.join(',')}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();

        bucketItems.innerHTML = data.data.map(product => `
            <article class="product-card" style="display: grid; grid-template-columns: 140px 1fr; overflow: hidden;">
                <img src="${product.cover_image_url || 'https://placehold.co/600x750?text=SCAK'}" alt="${product.name}" style="aspect-ratio: 1 / 1; height: 100%;">
                <div class="product-card-body">
                    <strong>${product.name}</strong>
                    <div class="tag-list">${(product.tags || []).map(tag => `<span class="pill">${tag}</span>`).join('')}</div>
                    <div>Rs. ${Number(product.price).toFixed(2)} x ${cart[product.id]}</div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <button class="btn-secondary" onclick="updateQuantity(${product.id}, -1)">-</button>
                        <span>${cart[product.id]}</span>
                        <button class="btn-secondary" onclick="updateQuantity(${product.id}, 1)">+</button>
                        <button class="btn-secondary" onclick="removeFromBucket(${product.id})">Remove</button>
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
        await renderBucket();
        bucketMessage.textContent = `Order placed. Reference code: ${data.reference_code}`;
    });

    renderBucket();
</script>
@endpush
