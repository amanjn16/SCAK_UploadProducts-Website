@extends('layouts.app', ['title' => 'SCAK Catalog'])

@section('content')
    <section class="grid">
        <div class="panel" style="padding: 20px;">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
                <strong style="font-size:1.05rem;">Catalog</strong>
                <div id="catalogCount" class="muted">Showing 0 / 0 products</div>
            </div>
            <div class="field" style="margin-top: 14px;">
                <label>Search</label>
                <input id="searchFilter" placeholder="Search by title, SKU, or tag">
            </div>
        </div>
        <div id="catalogResults"></div>
        <div id="catalogEmpty" class="panel empty-state" style="display: none;">No products match your filters yet.</div>
        <div id="catalogLoadingMore" class="panel" style="display: none; text-align: center;">Loading more products...</div>
        <div id="catalogSentinel" style="height: 1px;"></div>
    </section>

    <button class="btn-primary floating-filter-btn" id="filterFab" type="button" aria-label="Open filters">&#9776;</button>
    <div class="drawer-overlay" id="filterOverlay"></div>
    <aside class="drawer" id="filterDrawer">
        <div class="drawer-header">
            <strong>Filter Products</strong>
            <button class="btn-secondary" id="closeFilterDrawer" type="button">Close</button>
        </div>
        <div class="drawer-body">
            <div class="field">
                <label>Tags</label>
                <div id="tagFilterList" class="tag-list"></div>
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
    <a class="btn-primary cart-chip" href="{{ route('bucket') }}" id="cartChip" aria-label="Open cart">&#128722; <span>0</span></a>
@endsection

@push('scripts')
<script>
    let catalogProducts = [];
    let catalogCurrentPage = 1;
    let catalogLastPage = 1;
    let catalogTotal = 0;
    let catalogLoading = false;
    let catalogObserver;
    let selectedTagSlugs = [];
    function updateCartChip() {
        document.querySelector('#cartChip span').textContent = String(window.scakCart.count());
    }

    function productCard(product) {
        const button = product.is_active
            ? `<button class="btn-primary" onclick="window.scakCart.add(${product.id}); updateCartChip();">Add to Cart</button>`
            : `<button class="btn-secondary" disabled>Archived</button>`;

        return `
            <article class="product-card">
                <a href="/catalog/${product.slug}">
                    <img src="${product.cover_image_url || 'https://placehold.co/600x750?text=SCAK'}" alt="${product.name}">
                </a>
                <div class="product-card-body">
                    <strong>${product.name}</strong>
                    <div style="font-size: 1.1rem;">Rs. ${Number(product.price).toFixed(2)}</div>
                    ${button}
                </div>
            </article>
        `;
    }

    function renderGroupedProducts(products) {
        const hasFilters = selectedTagSlugs.length
            || document.getElementById('minPriceFilter').value
            || document.getElementById('maxPriceFilter').value
            || document.getElementById('searchFilter').value
            || document.getElementById('showArchiveFilter').checked
            || document.getElementById('sortFilter').value;

        if (hasFilters) {
            return `<div class="product-grid">${products.map(productCard).join('')}</div>`;
        }

        const groups = products.reduce((carry, product) => {
            const dateSource = product.legacy_published_at || product.published_at || product.created_at || Date.now();
            const dateLabel = new Date(dateSource).toLocaleDateString(undefined, {
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
        const tagFilterList = document.getElementById('tagFilterList');

        (data.tags || []).forEach(option => {
            const id = `tag-filter-${option.slug}`;
            const label = document.createElement('label');
            label.className = 'pill';
            label.style.justifyContent = 'flex-start';
            label.innerHTML = `<input type="checkbox" value="${option.slug}" id="${id}" style="width:auto;"> ${option.name} (${option.products_count})`;
            tagFilterList.appendChild(label);
        });

        if (data.price) {
            document.getElementById('minPriceFilter').placeholder = String(Math.floor(data.price.min || 0));
            document.getElementById('maxPriceFilter').placeholder = String(Math.ceil(data.price.max || 0));
        }
    }

    function buildProductParams() {
        const params = new URLSearchParams();
        const search = document.getElementById('searchFilter').value;
        const sort = document.getElementById('sortFilter').value;
        const minPrice = document.getElementById('minPriceFilter').value;
        const maxPrice = document.getElementById('maxPriceFilter').value;
        const showArchive = document.getElementById('showArchiveFilter').checked;

        if (search) params.set('search', search);
        if (sort) params.set('sort', sort);
        selectedTagSlugs.forEach(tag => params.append('tags[]', tag));
        if (minPrice) params.set('min_price', minPrice);
        if (maxPrice) params.set('max_price', maxPrice);
        if (showArchive) params.set('include_archived', '1');

        return params;
    }

    function syncCatalogView() {
        const results = document.getElementById('catalogResults');
        const empty = document.getElementById('catalogEmpty');
        const loadingMore = document.getElementById('catalogLoadingMore');
        const count = document.getElementById('catalogCount');

        results.innerHTML = '';

        if (!catalogProducts.length) {
            empty.style.display = 'block';
            loadingMore.style.display = 'none';
            count.textContent = 'Showing 0 / 0 products';
            return;
        }

        empty.style.display = 'none';
        results.innerHTML = renderGroupedProducts(catalogProducts);
        loadingMore.style.display = catalogCurrentPage < catalogLastPage ? 'block' : 'none';
        count.textContent = `Showing ${catalogProducts.length} / ${Number.isFinite(catalogTotal) ? catalogTotal : catalogProducts.length} products`;
    }

    async function loadProducts(reset = true) {
        if (catalogLoading) return;

        if (reset) {
            catalogProducts = [];
            catalogCurrentPage = 1;
            catalogLastPage = 1;
            catalogTotal = 0;
            syncCatalogView();
        }

        catalogLoading = true;
        const params = buildProductParams();
        params.set('page', String(catalogCurrentPage));

        const response = await fetch(`/products?${params.toString()}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();

        catalogCurrentPage = data.current_page || 1;
        catalogLastPage = data.last_page || 1;
        catalogTotal = data.total || catalogProducts.length;

        if (Array.isArray(data.data) && data.data.length) {
            const seen = new Set(catalogProducts.map(product => product.id));
            const nextProducts = data.data.filter(product => !seen.has(product.id));
            catalogProducts = [...catalogProducts, ...nextProducts];
        }

        catalogLoading = false;
        syncCatalogView();

        if (reset) {
            closeDrawer();
        }
    }

    async function loadMoreProducts() {
        if (catalogLoading || catalogCurrentPage >= catalogLastPage) return;

        catalogCurrentPage += 1;
        await loadProducts(false);
    }

    function setupInfiniteScroll() {
        const sentinel = document.getElementById('catalogSentinel');
        if (!sentinel) return;

        if (catalogObserver) {
            catalogObserver.disconnect();
        }

        catalogObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadMoreProducts();
                }
            });
        }, { rootMargin: '240px 0px' });

        catalogObserver.observe(sentinel);
    }

    function closeDrawer() {
        document.getElementById('filterDrawer').classList.remove('open');
        document.getElementById('filterOverlay').classList.remove('open');
    }

    document.getElementById('applyFiltersButton').addEventListener('click', () => loadProducts(true));
    document.getElementById('clearFiltersButton').addEventListener('click', () => {
        selectedTagSlugs = [];
        document.querySelectorAll('#tagFilterList input[type="checkbox"]').forEach(input => {
            input.checked = false;
        });
        document.getElementById('minPriceFilter').value = '';
        document.getElementById('maxPriceFilter').value = '';
        document.getElementById('showArchiveFilter').checked = false;
        document.getElementById('sortFilter').value = '';
        loadProducts(true);
    });
    document.getElementById('searchFilter').addEventListener('keydown', event => {
        if (event.key === 'Enter') loadProducts(true);
    });
    document.getElementById('filterFab').addEventListener('click', () => {
        document.getElementById('filterDrawer').classList.add('open');
        document.getElementById('filterOverlay').classList.add('open');
    });
    document.getElementById('closeFilterDrawer').addEventListener('click', closeDrawer);
    document.getElementById('filterOverlay').addEventListener('click', closeDrawer);
    document.getElementById('tagFilterList').addEventListener('change', () => {
        selectedTagSlugs = Array.from(document.querySelectorAll('#tagFilterList input[type="checkbox"]:checked'))
            .map(input => input.value);
    });

    updateCartChip();
    setupInfiniteScroll();
    loadFilters().then(() => loadProducts(true));
</script>
@endpush
