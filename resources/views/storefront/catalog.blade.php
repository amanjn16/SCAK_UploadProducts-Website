@extends('layouts.app', ['title' => 'SCAK Catalog'])

@section('content')
    <section class="grid">
        <div class="panel" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div>
                    <span class="pill">Verified customer</span>
                    <h1 style="margin: 12px 0 6px;">Welcome, {{ $customer->name }}</h1>
                    <p class="muted" style="margin: 0;">Browse products, filter by tags and rate, add what you like to cart, and place one order for offline follow-up.</p>
                </div>
                <a class="btn-primary" href="{{ route('bucket') }}">Open Cart</a>
            </div>
            <div class="field" style="margin-top: 18px;">
                <label>Search</label>
                <input id="searchFilter" placeholder="Search by title, SKU, or tag">
            </div>
        </div>
        <div id="catalogResults"></div>
        <div id="catalogEmpty" class="panel empty-state" style="display: none;">No products match your filters yet.</div>
    </section>

    <button class="btn-primary floating-filter-btn" id="filterFab" type="button">Filter</button>
    <div class="drawer-overlay" id="filterOverlay"></div>
    <aside class="drawer" id="filterDrawer">
        <div class="drawer-header">
            <strong>Filter Products</strong>
            <button class="btn-secondary" id="closeFilterDrawer" type="button">Close</button>
        </div>
        <div class="drawer-body">
            <div class="field">
                <label>Tag</label>
                <select id="tagFilter"><option value="">All tags</option></select>
            </div>
            <div class="field">
                <label>Minimum rate</label>
                <input id="minPriceFilter" inputmode="numeric" placeholder="0">
            </div>
            <div class="field">
                <label>Maximum rate</label>
                <input id="maxPriceFilter" inputmode="numeric" placeholder="0">
            </div>
            <label class="pill" style="justify-content: flex-start;">
                <input id="showArchiveFilter" type="checkbox" style="width:auto;">
                Show Archive
            </label>
            <div class="field">
                <label>Sort</label>
                <select id="sortFilter">
                    <option value="">Latest</option>
                    <option value="price_low">Rate: Low to High</option>
                    <option value="price_high">Rate: High to Low</option>
                    <option value="title">Title</option>
                </select>
            </div>
            <button class="btn-primary" id="applyFiltersButton" type="button">Apply Filters</button>
            <button class="btn-secondary" id="clearFiltersButton" type="button">Clear Filters</button>
        </div>
    </aside>
    <a class="btn-primary cart-chip" href="{{ route('bucket') }}" id="cartChip">Cart (0)</a>
@endsection

@push('scripts')
<script>
    function updateCartChip() {
        document.getElementById('cartChip').textContent = `Cart (${window.scakCart.count()})`;
    }

    function productCard(product) {
        const tags = (product.tags || []).map(tag => `<span class="pill">${tag}</span>`).join('');
        const archiveBadge = product.is_active ? '' : '<span class="pill" style="background:#efe4d2;">Archived</span>';
        const button = product.is_active
            ? `<button class="btn-primary" onclick="window.scakCart.add(${product.id}); updateCartChip();">Add to Cart</button>`
            : `<button class="btn-secondary" disabled>Archived</button>`;

        return `
            <article class="product-card">
                <a href="/catalog/${product.slug}">
                    <img src="${product.cover_image_url || 'https://placehold.co/600x750?text=SCAK'}" alt="${product.name}">
                </a>
                <div class="product-card-body">
                    <div style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
                        <div class="muted">${product.sku || 'SCAK'}</div>
                        ${archiveBadge}
                    </div>
                    <strong>${product.name}</strong>
                    <div class="tag-list">${tags}</div>
                    <div style="font-size: 1.1rem;">Rs. ${Number(product.price).toFixed(2)}</div>
                    ${button}
                </div>
            </article>
        `;
    }

    function renderGroupedProducts(products) {
        const hasFilters = document.getElementById('tagFilter').value
            || document.getElementById('minPriceFilter').value
            || document.getElementById('maxPriceFilter').value
            || document.getElementById('searchFilter').value
            || document.getElementById('showArchiveFilter').checked
            || document.getElementById('sortFilter').value;

        if (hasFilters) {
            return `<div class="product-grid">${products.map(productCard).join('')}</div>`;
        }

        const groups = products.reduce((carry, product) => {
            const dateLabel = new Date(product.created_at || Date.now()).toLocaleDateString(undefined, {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });

            carry[dateLabel] = carry[dateLabel] || [];
            carry[dateLabel].push(product);
            return carry;
        }, {});

        return Object.entries(groups).map(([dateLabel, items]) => `
            <section>
                <div class="tile-date">${dateLabel}</div>
                <div class="product-grid">${items.map(productCard).join('')}</div>
            </section>
        `).join('');
    }

    async function loadFilters() {
        const response = await fetch('{{ route('filters.index') }}', { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const tagSelect = document.getElementById('tagFilter');

        (data.tags || []).forEach(option => {
            const element = document.createElement('option');
            element.value = option.slug;
            element.textContent = `${option.name} (${option.products_count})`;
            tagSelect.appendChild(element);
        });

        if (data.price) {
            document.getElementById('minPriceFilter').placeholder = String(Math.floor(data.price.min || 0));
            document.getElementById('maxPriceFilter').placeholder = String(Math.ceil(data.price.max || 0));
        }
    }

    async function loadProducts() {
        const params = new URLSearchParams();
        const search = document.getElementById('searchFilter').value;
        const sort = document.getElementById('sortFilter').value;
        const tag = document.getElementById('tagFilter').value;
        const minPrice = document.getElementById('minPriceFilter').value;
        const maxPrice = document.getElementById('maxPriceFilter').value;
        const showArchive = document.getElementById('showArchiveFilter').checked;

        if (search) params.set('search', search);
        if (sort) params.set('sort', sort);
        if (tag) params.set('tag', tag);
        if (minPrice) params.set('min_price', minPrice);
        if (maxPrice) params.set('max_price', maxPrice);
        if (showArchive) params.set('include_archived', '1');

        const response = await fetch(`/products?${params.toString()}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const results = document.getElementById('catalogResults');
        const empty = document.getElementById('catalogEmpty');

        results.innerHTML = '';

        if (!data.data || data.data.length === 0) {
            empty.style.display = 'block';
            return;
        }

        empty.style.display = 'none';
        results.innerHTML = renderGroupedProducts(data.data);
        closeDrawer();
    }

    function closeDrawer() {
        document.getElementById('filterDrawer').classList.remove('open');
        document.getElementById('filterOverlay').classList.remove('open');
    }

    document.getElementById('applyFiltersButton').addEventListener('click', loadProducts);
    document.getElementById('clearFiltersButton').addEventListener('click', () => {
        document.getElementById('tagFilter').value = '';
        document.getElementById('minPriceFilter').value = '';
        document.getElementById('maxPriceFilter').value = '';
        document.getElementById('showArchiveFilter').checked = false;
        document.getElementById('sortFilter').value = '';
        loadProducts();
    });
    document.getElementById('searchFilter').addEventListener('keydown', event => {
        if (event.key === 'Enter') loadProducts();
    });
    document.getElementById('filterFab').addEventListener('click', () => {
        document.getElementById('filterDrawer').classList.add('open');
        document.getElementById('filterOverlay').classList.add('open');
    });
    document.getElementById('closeFilterDrawer').addEventListener('click', closeDrawer);
    document.getElementById('filterOverlay').addEventListener('click', closeDrawer);

    updateCartChip();
    loadFilters().then(loadProducts);
</script>
@endpush
