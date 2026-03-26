@extends('layouts.app', ['title' => 'SCAK Catalog'])

@section('content')
    <section class="sidebar-layout">
        <aside class="filters panel">
            <h2 style="margin-top: 0;">Filter Products</h2>
            <div class="grid">
                <div class="field">
                    <label>Search</label>
                    <input id="searchFilter" placeholder="Name or SKU">
                </div>
                <div class="field">
                    <label>Supplier</label>
                    <select id="supplierFilter"><option value="">All suppliers</option></select>
                </div>
                <div class="field">
                    <label>City</label>
                    <select id="cityFilter"><option value="">All cities</option></select>
                </div>
                <div class="field">
                    <label>Category</label>
                    <select id="categoryFilter"><option value="">All categories</option></select>
                </div>
                <div class="field">
                    <label>Top Fabric</label>
                    <select id="topFabricFilter"><option value="">All top fabrics</option></select>
                </div>
                <div class="field">
                    <label>Dupatta Fabric</label>
                    <select id="dupattaFabricFilter"><option value="">All dupatta fabrics</option></select>
                </div>
                <div class="field">
                    <label>Size</label>
                    <select id="sizeFilter"><option value="">All sizes</option></select>
                </div>
                <div class="field">
                    <label>Feature</label>
                    <select id="featureFilter"><option value="">All features</option></select>
                </div>
                <div class="field">
                    <label>Sort</label>
                    <select id="sortFilter">
                        <option value="">Latest</option>
                        <option value="price_low">Price: Low to High</option>
                        <option value="price_high">Price: High to Low</option>
                        <option value="title">Title</option>
                    </select>
                </div>
                <button class="btn-primary" id="applyFiltersButton">Apply</button>
            </div>
        </aside>
        <section class="grid">
            <div class="panel" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <span class="pill">Verified customer</span>
                        <h1 style="margin: 12px 0 6px;">Welcome, {{ $customer->name }}</h1>
                        <p class="muted" style="margin: 0;">Browse products, shortlist them into your bucket, then send one order request for offline follow-up.</p>
                    </div>
                    <a class="btn-primary" href="{{ route('bucket') }}">Open Bucket</a>
                </div>
            </div>
            <div id="catalogResults" class="product-grid"></div>
            <div id="catalogEmpty" class="panel empty-state" style="display: none;">No products match your filters yet.</div>
        </section>
    </section>
    <a class="btn-primary cart-chip" href="{{ route('bucket') }}" id="cartChip">Bucket (0)</a>
@endsection

@push('scripts')
<script>
    const selects = {
        suppliers: document.getElementById('supplierFilter'),
        cities: document.getElementById('cityFilter'),
        categories: document.getElementById('categoryFilter'),
        top_fabrics: document.getElementById('topFabricFilter'),
        dupatta_fabrics: document.getElementById('dupattaFabricFilter'),
        sizes: document.getElementById('sizeFilter'),
        features: document.getElementById('featureFilter'),
    };

    const fieldToParam = {
        suppliers: 'supplier',
        cities: 'city',
        categories: 'category',
        top_fabrics: 'top_fabric',
        dupatta_fabrics: 'dupatta_fabric',
        sizes: 'size',
        features: 'feature',
    };

    function updateCartChip() {
        document.getElementById('cartChip').textContent = `Bucket (${window.scakCart.count()})`;
    }

    function productCard(product) {
        return `
            <article class="product-card">
                <a href="/catalog/${product.slug}">
                    <img src="${product.cover_image_url || 'https://placehold.co/600x750?text=SCAK'}" alt="${product.name}">
                </a>
                <div class="product-card-body">
                    <div class="muted">${product.supplier || 'SCAK'} · ${product.city || '-'}</div>
                    <strong>${product.name}</strong>
                    <div style="font-size: 1.1rem;">Rs. ${Number(product.price).toFixed(2)}</div>
                    <button class="btn-primary" onclick="window.scakCart.add(${product.id}); updateCartChip();">Add to Bucket</button>
                </div>
            </article>
        `;
    }

    async function loadFilters() {
        const response = await fetch('{{ route('filters.index') }}', { headers: { Accept: 'application/json' } });
        const data = await response.json();

        Object.entries(selects).forEach(([key, select]) => {
            (data[key] || []).forEach(option => {
                const element = document.createElement('option');
                element.value = option.slug;
                element.textContent = option.name;
                select.appendChild(element);
            });
        });
    }

    async function loadProducts() {
        const params = new URLSearchParams();
        const search = document.getElementById('searchFilter').value;
        const sort = document.getElementById('sortFilter').value;

        if (search) params.set('search', search);
        if (sort) params.set('sort', sort);

        Object.entries(selects).forEach(([key, select]) => {
            if (select.value) params.set(fieldToParam[key], select.value);
        });

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
        results.innerHTML = data.data.map(productCard).join('');
    }

    document.getElementById('applyFiltersButton').addEventListener('click', loadProducts);
    document.getElementById('searchFilter').addEventListener('keydown', event => {
        if (event.key === 'Enter') loadProducts();
    });

    updateCartChip();
    loadFilters().then(loadProducts);
</script>
@endpush
