@extends('layouts.app')

@section('content')
    <section class="admin-shell panel">
        <div class="admin-header">
            <button class="btn-secondary icon-btn admin-menu-button" id="adminMenuButton" aria-label="Open admin menu">
                &#9776;
            </button>
            <div class="admin-heading">
                <h1 id="adminCurrentTitle">Products</h1>
                <span class="admin-subtle">Web admin</span>
            </div>
            <div class="admin-user">
                <strong>{{ $customer?->name }}</strong>
                <span>{{ $customer?->role }}</span>
            </div>
        </div>

        <div class="admin-status" id="adminStatus"></div>
        <div class="admin-content" id="adminContent"></div>
    </section>

    <div class="admin-drawer-shell" id="adminDrawerShell" hidden>
        <aside class="admin-drawer panel">
            <div class="admin-drawer-head">
                <div>
                    <strong>Admin Menu</strong>
                    <div class="admin-meta">{{ $customer?->phone }}</div>
                </div>
                <button class="btn-secondary" id="closeAdminDrawerButton">Close</button>
            </div>
            <div class="admin-drawer-links" id="adminTabs"></div>
            <div class="admin-drawer-links admin-drawer-links-secondary">
                <a href="{{ route('catalog') }}" class="admin-drawer-link">Back To Catalog</a>
                <a href="{{ route('apps.index') }}" class="admin-drawer-link">Apps</a>
            </div>
        </aside>
    </div>

    <div class="admin-modal-shell" id="adminModalShell" hidden>
        <div class="admin-modal panel" id="adminModal"></div>
    </div>
@endsection

@push('head')
    <style>
        .admin-shell {
            padding: 18px;
            display: grid;
            gap: 16px;
        }
        .admin-header {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .admin-header h1 {
            margin: 0;
            font-size: clamp(1.45rem, 2.4vw, 2.2rem);
        }
        .admin-heading {
            display: grid;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        .admin-subtle {
            color: #6d5842;
            font-size: 0.85rem;
        }
        .admin-user {
            display: grid;
            gap: 2px;
            min-width: 110px;
            justify-items: end;
            color: #6d5842;
            font-size: 0.82rem;
        }
        .admin-menu-button {
            width: 46px;
            height: 46px;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }
        .admin-drawer-shell {
            position: fixed;
            inset: 0;
            background: rgba(31, 42, 55, 0.3);
            backdrop-filter: blur(4px);
            z-index: 45;
            display: flex;
        }
        .admin-drawer-shell[hidden] {
            display: none !important;
        }
        .admin-drawer {
            width: min(320px, calc(100vw - 24px));
            border-radius: 0 24px 24px 0;
            padding: 18px;
            display: grid;
            gap: 14px;
            max-height: 100dvh;
            overflow: auto;
        }
        .admin-drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .admin-drawer-links {
            display: grid;
            gap: 10px;
        }
        .admin-drawer-links-secondary {
            padding-top: 10px;
            border-top: 1px solid var(--line);
        }
        .admin-drawer-link,
        .admin-tab {
            border: 1px solid var(--line);
            background: white;
            color: var(--ink);
            border-radius: 16px;
            padding: 12px 14px;
            cursor: pointer;
            font-weight: 600;
            text-align: left;
        }
        .admin-tab.active {
            background: var(--accent);
            color: white;
            border-color: transparent;
        }
        .admin-status {
            display: none;
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff1ea;
            color: var(--accent-dark);
            font-weight: 600;
        }
        .admin-status.visible {
            display: block;
        }
        .admin-toolbar {
            display: grid;
            gap: 14px;
            margin-bottom: 18px;
        }
        .admin-toolbar-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .admin-toolbar-row .field {
            min-width: 0;
            flex: 1 1 220px;
        }
        .admin-grid {
            display: grid;
            gap: 16px;
        }
        .admin-grid.products {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .admin-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 12px;
            display: grid;
            gap: 8px;
        }
        .admin-card.compact {
            gap: 6px;
        }
        .admin-card img {
            width: 100%;
            aspect-ratio: 4/5;
            object-fit: cover;
            border-radius: 16px;
            background: #f3ebe0;
        }
        .admin-card-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .admin-card-title {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.15;
        }
        .admin-meta {
            color: #6d5842;
            font-size: 0.83rem;
        }
        .admin-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .admin-actions .btn,
        .admin-actions button {
            padding: 10px 12px;
            font-size: 0.9rem;
        }
        .admin-selection {
            display: flex;
            gap: 8px;
            align-items: center;
            color: #6d5842;
            font-size: 0.9rem;
        }
        .admin-list {
            display: grid;
            gap: 12px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255,255,255,0.92);
            border-radius: 20px;
            overflow: hidden;
        }
        .admin-table th,
        .admin-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
            text-align: left;
            font-size: 0.92rem;
        }
        .admin-table th {
            background: #efe0cd;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .admin-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            background: #f4ece0;
            font-size: 0.8rem;
            color: #6d5842;
        }
        .admin-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #d12b2b;
            display: inline-block;
        }
        .admin-modal-shell {
            position: fixed;
            inset: 0;
            background: rgba(31, 42, 55, 0.45);
            backdrop-filter: blur(6px);
            display: grid;
            place-items: center;
            z-index: 50;
            padding: 16px;
        }
        .admin-modal-shell[hidden] {
            display: none !important;
        }
        .admin-modal {
            width: min(960px, 100%);
            max-height: calc(100dvh - 32px);
            overflow: auto;
            padding: 22px;
            display: grid;
            gap: 18px;
        }
        .admin-modal-header {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
        }
        .admin-modal-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .admin-modal-grid .field.full {
            grid-column: 1 / -1;
        }
        .admin-existing-images,
        .admin-staged-images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }
        .admin-image-card {
            background: #fffdfa;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 10px;
            display: grid;
            gap: 8px;
        }
        .admin-image-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 12px;
        }
        .admin-image-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .admin-subtabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .admin-empty {
            padding: 30px 18px;
            text-align: center;
            color: #6d5842;
            border: 1px dashed var(--line);
            border-radius: 20px;
            background: rgba(255,255,255,0.6);
        }
        .admin-summary-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .admin-summary-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px;
            display: grid;
            gap: 8px;
        }
        .admin-summary-card strong {
            font-size: 1.6rem;
        }
        .admin-inline {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .admin-link {
            color: var(--accent);
            font-weight: 700;
        }
        @media (max-width: 900px) {
            .admin-header {
                align-items: center;
            }
            .admin-user {
                justify-items: end;
            }
            .admin-modal-grid {
                grid-template-columns: 1fr;
            }
            .admin-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .admin-grid.products {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }
            .admin-shell {
                padding: 14px;
            }
            .admin-header h1 {
                font-size: 1.8rem;
            }
            .admin-user {
                font-size: 0.74rem;
                min-width: 80px;
            }
            .admin-toolbar {
                gap: 10px;
            }
            .admin-toolbar-row {
                gap: 8px;
            }
            .admin-toolbar-row .field {
                flex: 1 1 100%;
            }
            .admin-actions button,
            .admin-toolbar button,
            .admin-selection {
                font-size: 0.82rem;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const tabLabels = {
                products: 'Products',
                orders: 'Orders',
                customers: 'Customers',
                tags: 'Tags',
                admins: 'Admins',
                activity: 'Activity',
                visitors: 'Visitors',
                analytics: 'Analytics',
                batches: 'Batches',
                settings: 'Settings',
                health: 'Health',
            };

            const state = {
                currentTab: 'products',
                isSuperAdmin: @json($isSuperAdmin),
                tabs: ['products', 'orders', 'customers', 'tags', 'admins', 'activity', 'visitors', 'analytics', 'batches', 'settings', 'health'],
                products: [],
                productsMeta: { current_page: 1, last_page: 1, total: 0 },
                productSearch: '',
                productIncludeArchived: true,
                selectedProducts: new Set(),
                selectionMode: false,
                currentProduct: null,
                orderMode: 'active',
                orders: { active: [], archived: [] },
                customers: [],
                customerMeta: { current_page: 1, last_page: 1, total: 0 },
                customerSearch: '',
                tags: [],
                admins: [],
                activity: [],
                activityMeta: { current_page: 1, last_page: 1, total: 0 },
                visitors: [],
                visitorMeta: { current_page: 1, last_page: 1, total: 0 },
                analytics: [],
                analyticsMeta: { current_page: 1, last_page: 1, total: 0 },
                batches: [],
                settings: { group_links: [], marquee_speed_seconds: 9.6 },
                releases: null,
                health: null,
            };

            const elements = {
                tabs: document.getElementById('adminTabs'),
                content: document.getElementById('adminContent'),
                status: document.getElementById('adminStatus'),
                currentTitle: document.getElementById('adminCurrentTitle'),
                drawerShell: document.getElementById('adminDrawerShell'),
                modalShell: document.getElementById('adminModalShell'),
                modal: document.getElementById('adminModal'),
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function formatCurrency(value) {
                const amount = Number(value || 0);
                return Number.isInteger(amount) ? `Rs. ${amount}` : `Rs. ${amount.toFixed(2)}`;
            }

            function showStatus(message, isError = false) {
                if (!message) {
                    elements.status.classList.remove('visible');
                    elements.status.textContent = '';
                    elements.status.style.background = '#fff1ea';
                    elements.status.style.color = 'var(--accent-dark)';
                    return;
                }

                elements.status.textContent = message;
                elements.status.classList.add('visible');
                elements.status.style.background = isError ? '#ffe8e8' : '#fff1ea';
                elements.status.style.color = isError ? '#8b1f1f' : 'var(--accent-dark)';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            async function api(path, options = {}) {
                const headers = {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    ...(options.headers || {}),
                };

                const response = await fetch(path, {
                    credentials: 'same-origin',
                    ...options,
                    headers,
                });

                const contentType = response.headers.get('content-type') || '';
                const body = contentType.includes('application/json')
                    ? await response.json()
                    : await response.text();

                if (!response.ok) {
                    const message = typeof body === 'string'
                        ? body
                        : body.message || body.error || response.statusText;
                    throw new Error(message);
                }

                return body;
            }

            function renderTabs() {
                elements.tabs.innerHTML = state.tabs.map((tab) => `
                    <button class="admin-tab ${state.currentTab === tab ? 'active' : ''}" data-tab="${tab}">
                        ${tabLabels[tab]}
                    </button>
                `).join('');
                elements.currentTitle.textContent = tabLabels[state.currentTab] || 'Admin';
            }

            async function setTab(tab) {
                state.currentTab = tab;
                renderTabs();
                closeDrawer();
                await loadCurrentTab();
            }

            function openDrawer() {
                elements.drawerShell.hidden = false;
            }

            function closeDrawer() {
                elements.drawerShell.hidden = true;
            }

            async function loadCurrentTab() {
                try {
                    switch (state.currentTab) {
                        case 'products':
                            await loadProducts();
                            renderProducts();
                            break;
                        case 'orders':
                            await loadOrders();
                            renderOrders();
                            break;
                        case 'customers':
                            await loadCustomers();
                            renderCustomers();
                            break;
                        case 'tags':
                            await loadTags();
                            renderTags();
                            break;
                        case 'admins':
                            await loadAdmins();
                            renderAdmins();
                            break;
                        case 'activity':
                            await loadActivity();
                            renderActivity();
                            break;
                        case 'visitors':
                            await loadVisitors();
                            renderVisitors();
                            break;
                        case 'analytics':
                            await loadAnalytics();
                            renderAnalytics();
                            break;
                        case 'batches':
                            await loadBatches();
                            renderBatches();
                            break;
                        case 'settings':
                            await loadSettings();
                            renderSettings();
                            break;
                        case 'health':
                            await loadHealth();
                            renderHealth();
                            break;
                    }
                } catch (error) {
                    showStatus(error.message || 'Something went wrong.', true);
                }
            }

            async function loadProducts(page = 1) {
                const params = new URLSearchParams();
                params.set('page', page);
                params.set('per_page', 50);
                params.set('include_archived', state.productIncludeArchived ? '1' : '0');
                if (state.productSearch.trim()) {
                    params.set('search', state.productSearch.trim());
                }
                const response = await api(`/admin/products?${params.toString()}`);
                state.products = response.data;
                state.productsMeta = response;
            }

            async function loadOrders() {
                const [active, archived] = await Promise.all([
                    api('/admin/order-requests?is_archived=0&page=1&per_page=50'),
                    api('/admin/order-requests?is_archived=1&page=1&per_page=50'),
                ]);
                state.orders.active = active.data;
                state.orders.archived = archived.data;
            }

            async function loadCustomers(page = 1) {
                const params = new URLSearchParams({ page, per_page: 25 });
                if (state.customerSearch.trim()) {
                    params.set('search', state.customerSearch.trim());
                }
                const response = await api(`/admin/customers?${params.toString()}`);
                state.customers = response.data;
                state.customerMeta = response;
            }

            async function loadTags() {
                const response = await api('/admin/tags');
                state.tags = response.data;
            }

            async function loadAdmins() {
                const response = await api('/admin/admin-users');
                state.admins = response.data;
            }

            async function loadActivity(page = 1) {
                const response = await api(`/admin/activity-logs?page=${page}&per_page=25`);
                state.activity = response.data;
                state.activityMeta = response;
            }

            async function loadVisitors(page = 1) {
                const response = await api(`/admin/visitor-sessions?page=${page}&per_page=25`);
                state.visitors = response.data;
                state.visitorMeta = response;
            }

            async function loadAnalytics(page = 1) {
                const response = await api(`/admin/legacy-analytics?page=${page}&per_page=25`);
                state.analytics = response.data;
                state.analyticsMeta = response;
            }

            async function loadBatches() {
                const response = await api('/admin/product-batches');
                state.batches = response.data;
            }

            async function loadSettings() {
                const [settings, releases] = await Promise.all([
                    api('/admin/settings/storefront'),
                    api('/admin/settings/app-releases'),
                ]);
                state.settings = settings.data;
                state.releases = releases.data;
            }

            async function loadHealth() {
                const response = await api('/admin/system-health');
                state.health = response.data;
            }

            function chooseOverlayMode(actionLabel) {
                return window.confirm(`Include rates on images for ${actionLabel.toLowerCase()}? Click OK for rates, Cancel for plain images.`);
            }

            function rateLabel(price) {
                const amount = Number(price || 0);
                return Number.isInteger(amount) ? `Rs. ${amount}` : `Rs. ${amount.toFixed(2)}`;
            }

            function shareExportEntries(products) {
                return products.flatMap((product) =>
                    (product.images || []).map((image, index) => ({
                        productName: product.name,
                        price: product.price,
                        url: image.url,
                        filename: `${product.name || 'product'}-${String(index + 1).padStart(2, '0')}.jpg`,
                    }))
                );
            }

            async function loadImageBlob(url) {
                const response = await fetch(url, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error(`Could not load image: ${url}`);
                }

                return await response.blob();
            }

            async function renderOverlayBlob(blob, price) {
                const objectUrl = URL.createObjectURL(blob);
                try {
                    const image = await new Promise((resolve, reject) => {
                        const element = new Image();
                        element.onload = () => resolve(element);
                        element.onerror = () => reject(new Error('Unable to render image for sharing.'));
                        element.src = objectUrl;
                    });

                    const canvas = document.createElement('canvas');
                    canvas.width = image.naturalWidth || image.width;
                    canvas.height = image.naturalHeight || image.height;
                    const context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0, canvas.width, canvas.height);

                    const barHeight = Math.max(56, Math.round(canvas.height * 0.075));
                    const barY = canvas.height - barHeight - Math.max(12, Math.round(canvas.height * 0.02));
                    context.fillStyle = 'rgba(42, 29, 20, 0.35)';
                    context.fillRect(0, barY, canvas.width, barHeight);
                    context.font = `600 ${Math.max(22, Math.round(canvas.width * 0.045))}px Arial`;
                    context.fillStyle = '#ffffff';
                    context.textAlign = 'center';
                    context.textBaseline = 'middle';
                    context.fillText(rateLabel(price), canvas.width / 2, barY + barHeight / 2);

                    return await new Promise((resolve, reject) => {
                        canvas.toBlob((output) => output ? resolve(output) : reject(new Error('Unable to create share image.')), 'image/jpeg', 0.92);
                    });
                } finally {
                    URL.revokeObjectURL(objectUrl);
                }
            }

            async function prepareShareFiles(includeRateOverlay) {
                if (!state.selectedProducts.size) {
                    throw new Error('Select at least one product first.');
                }

                showStatus('Preparing selected product images...');
                const response = await api('/admin/products/share-images', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_ids: Array.from(state.selectedProducts),
                        include_rate_overlay: includeRateOverlay,
                    }),
                });

                const entries = shareExportEntries(response.data || []);
                const files = [];
                for (const entry of entries) {
                    const sourceBlob = await loadImageBlob(entry.url);
                    const finalBlob = includeRateOverlay ? await renderOverlayBlob(sourceBlob, entry.price) : sourceBlob;
                    files.push(new File([finalBlob], entry.filename, { type: finalBlob.type || 'image/jpeg' }));
                }

                return files;
            }

            async function downloadFilesToBrowser(files) {
                for (const file of files) {
                    const fileUrl = URL.createObjectURL(file);
                    const anchor = document.createElement('a');
                    anchor.href = fileUrl;
                    anchor.download = file.name;
                    anchor.rel = 'noopener';
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    setTimeout(() => URL.revokeObjectURL(fileUrl), 1000);
                    await new Promise((resolve) => setTimeout(resolve, 120));
                }
            }

            async function shareSelectedProductsWeb() {
                const includeRateOverlay = chooseOverlayMode('Share');
                const files = await prepareShareFiles(includeRateOverlay);

                if (!files.length) {
                    throw new Error('No images found for the selected products.');
                }

                if (navigator.share && navigator.canShare && navigator.canShare({ files })) {
                    try {
                        await navigator.share({
                            files,
                            title: 'SCAK Product Images',
                            text: 'Shared from SCAK admin web app.',
                        });
                        showStatus('Share sheet opened.');
                        return;
                    } catch (error) {
                        await downloadFilesToBrowser(files);
                        showStatus('Direct sharing was blocked by this browser, so the images were downloaded instead.');
                        return;
                    }
                }

                await downloadFilesToBrowser(files);
                showStatus('Your browser cannot share files directly here, so the images were downloaded instead.');
            }

            async function downloadSelectedProductsWeb() {
                const includeRateOverlay = chooseOverlayMode('Download');
                const files = await prepareShareFiles(includeRateOverlay);

                if (!files.length) {
                    throw new Error('No images found for the selected products.');
                }

                await downloadFilesToBrowser(files);
                showStatus(`Downloaded ${files.length} image${files.length === 1 ? '' : 's'}.`);
            }

            function renderProducts() {
                elements.content.innerHTML = `
                    <div class="admin-toolbar">
                        <div class="admin-toolbar-row">
                            <div class="field">
                                <label>Search products</label>
                                <input id="productSearchInput" value="${escapeHtml(state.productSearch)}" placeholder="Search title, SKU, or tag">
                            </div>
                            <label class="admin-selection">
                                <input type="checkbox" id="productArchivedToggle" ${state.productIncludeArchived ? 'checked' : ''}>
                                Show archived
                            </label>
                            <button class="btn-secondary" id="productSearchButton">Search</button>
                            <button class="btn-secondary" id="productRefreshButton">Refresh</button>
                        </div>
                        <div class="admin-toolbar-row">
                            <label class="admin-selection">
                                <input type="checkbox" id="selectionModeToggle" ${state.selectionMode ? 'checked' : ''}>
                                Selection mode
                            </label>
                            <span class="admin-meta">Showing ${state.products.length} / ${state.productsMeta.total} products</span>
                            <span class="admin-meta">Page ${state.productsMeta.current_page} / ${state.productsMeta.last_page}</span>
                            ${state.selectionMode ? `
                                <button class="btn-secondary" id="bulkShareButton">Share</button>
                                <button class="btn-secondary" id="bulkDownloadButton">Download</button>
                                <button class="btn-secondary" id="bulkActivateButton">Activate</button>
                                <button class="btn-secondary" id="bulkArchiveButton">Archive</button>
                                ${state.isSuperAdmin ? '<button class="btn-secondary" id="bulkDeleteButton">Delete</button>' : ''}
                            ` : ''}
                        </div>
                    </div>
                    ${state.products.length ? `
                        <div class="admin-grid products">
                            ${state.products.map((product) => `
                                <article class="admin-card compact">
                                    <div class="admin-card-head">
                                        <h3 class="admin-card-title">${product.status === 'archived' || !product.is_active ? '<span class="admin-dot"></span> ' : ''}${escapeHtml(product.name)}</h3>
                                        ${state.selectionMode ? `<input type="checkbox" class="product-checkbox" data-product-id="${product.id}" ${state.selectedProducts.has(product.id) ? 'checked' : ''}>` : ''}
                                    </div>
                                    <img src="${escapeHtml(product.cover_image_thumb_url || product.cover_image_url || product.cover_image_original_url || '')}" alt="${escapeHtml(product.name)}">
                                    <div class="admin-meta">${escapeHtml(product.sku || '-')}</div>
                                    <strong>${formatCurrency(product.price)}</strong>
                                    ${product.remarks ? `<div class="admin-meta">${escapeHtml(product.remarks)}</div>` : ''}
                                    <div class="admin-actions">
                                        <button class="btn-secondary product-edit-button" data-product-id="${product.id}">Edit</button>
                                    </div>
                                </article>
                            `).join('')}
                        </div>
                    ` : '<div class="admin-empty">No products found for the current filters.</div>'}
                `;
            }

            function renderOrders() {
                const orders = state.orderMode === 'archived' ? state.orders.archived : state.orders.active;

                elements.content.innerHTML = `
                    <div class="admin-toolbar">
                        <div class="admin-toolbar-row">
                            <div class="admin-subtabs">
                                <button class="admin-tab ${state.orderMode === 'active' ? 'active' : ''}" data-order-mode="active">Active (${state.orders.active.length})</button>
                                <button class="admin-tab ${state.orderMode === 'archived' ? 'active' : ''}" data-order-mode="archived">Archived (${state.orders.archived.length})</button>
                            </div>
                        </div>
                    </div>
                    ${orders.length ? `
                        <div class="admin-list">
                            ${orders.map((order) => `
                                <article class="admin-card">
                                    <div class="admin-card-head">
                                        <div>
                                            <h3 class="admin-card-title">${escapeHtml(order.reference_code)}</h3>
                                            <div class="admin-meta">${escapeHtml(order.customer_name)} / ${escapeHtml(order.customer_phone)}</div>
                                        </div>
                                        <span class="admin-pill">${escapeHtml(order.status)}</span>
                                    </div>
                                    <div class="admin-meta">${order.internal_notes ? escapeHtml(order.internal_notes) : 'No remarks yet'}</div>
                                    <div class="admin-meta">Items: ${order.items.length}</div>
                                    <div class="admin-actions">
                                        <button class="btn-secondary order-open-button" data-order-id="${order.id}" data-archived="${order.is_archived ? 1 : 0}">Open</button>
                                    </div>
                                </article>
                            `).join('')}
                        </div>
                    ` : '<div class="admin-empty">No orders in this tab yet.</div>'}
                `;
            }

            function renderCustomers() {
                elements.content.innerHTML = `
                    <div class="admin-toolbar">
                        <div class="admin-toolbar-row">
                            <div class="field">
                                <label>Search customers</label>
                                <input id="customerSearchInput" value="${escapeHtml(state.customerSearch)}" placeholder="Search name or phone">
                            </div>
                            <button class="btn-secondary" id="customerSearchButton">Search</button>
                            <span class="admin-meta">Page ${state.customerMeta.current_page} / ${state.customerMeta.last_page} • ${state.customerMeta.total} rows</span>
                        </div>
                    </div>
                    ${state.customers.length ? `
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>City</th>
                                    <th>Orders</th>
                                    <th>Items</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                ${state.customers.map((customer) => `
                                    <tr>
                                        <td><strong>${escapeHtml(customer.name)}</strong><br><span class="admin-meta">${escapeHtml(customer.phone)}</span></td>
                                        <td>${escapeHtml(customer.city || '-')}</td>
                                        <td>${customer.total_orders}</td>
                                        <td>${customer.total_items_ordered}</td>
                                        <td><button class="btn-secondary customer-open-button" data-customer-id="${customer.id}">Open</button></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<div class="admin-empty">No customers matched your search.</div>'}
                `;
            }

            function renderTags() {
                elements.content.innerHTML = `
                    <div class="admin-toolbar">
                        <div class="admin-toolbar-row">
                            <div class="field">
                                <label>New tag</label>
                                <input id="newTagInput" placeholder="Enter tag name">
                            </div>
                            <button class="btn-primary" id="createTagButton">Add Tag</button>
                        </div>
                    </div>
                    <div class="admin-list">
                        ${state.tags.map((tag) => `
                            <article class="admin-card compact">
                                <div class="admin-card-head">
                                    <h3 class="admin-card-title">${escapeHtml(tag.name)} (${tag.products_count})</h3>
                                </div>
                                <div class="field">
                                    <label>Edit tag</label>
                                    <input value="${escapeHtml(tag.name)}" data-tag-edit-id="${tag.id}">
                                </div>
                                <div class="admin-actions">
                                    <button class="btn-secondary save-tag-button" data-tag-id="${tag.id}">Save</button>
                                    <button class="btn-secondary delete-tag-button" data-tag-id="${tag.id}">Delete</button>
                                </div>
                            </article>
                        `).join('')}
                    </div>
                `;
            }

            function renderAdmins() {
                elements.content.innerHTML = `
                    <div class="admin-toolbar">
                        ${state.isSuperAdmin ? `
                            <div class="admin-card">
                                <div class="admin-modal-grid">
                                    <div class="field">
                                        <label>Admin name</label>
                                        <input id="adminNameInput" placeholder="Name">
                                    </div>
                                    <div class="field">
                                        <label>Phone</label>
                                        <input id="adminPhoneInput" placeholder="Phone">
                                    </div>
                                    <div class="field">
                                        <label>City</label>
                                        <input id="adminCityInput" placeholder="City">
                                    </div>
                                    <div class="field">
                                        <label>Role</label>
                                        <select id="adminRoleInput">
                                            <option value="admin">Admin</option>
                                            <option value="super_admin">Super Admin</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="admin-actions">
                                    <button class="btn-primary" id="saveAdminButton">Save Admin</button>
                                </div>
                            </div>
                        ` : '<div class="admin-empty">Only super admins can add or remove admins from the web panel.</div>'}
                    </div>
                    <div class="admin-list">
                        ${state.admins.map((admin) => `
                            <article class="admin-card compact">
                                <h3 class="admin-card-title">${escapeHtml(admin.name)}</h3>
                                <div class="admin-meta">${escapeHtml(admin.phone)} / ${escapeHtml(admin.role)}</div>
                                <div class="admin-meta">${escapeHtml(admin.city || '-')}</div>
                                ${state.isSuperAdmin ? `
                                    <div class="admin-actions">
                                        <button class="btn-secondary delete-admin-button" data-admin-id="${admin.id}">Remove</button>
                                    </div>
                                ` : ''}
                            </article>
                        `).join('')}
                    </div>
                `;
            }

            function renderActivity() {
                elements.content.innerHTML = renderLogTable('Activity Logs', state.activity, state.activityMeta, (entry) => `
                    <tr>
                        <td>${escapeHtml(entry.action)}</td>
                        <td>${escapeHtml(entry.user?.name || '-')}</td>
                        <td>${escapeHtml(entry.user?.phone || '-')}</td>
                        <td>${escapeHtml(entry.created_at || '-')}</td>
                        <td>${escapeHtml(JSON.stringify(entry.meta || {}))}</td>
                    </tr>
                `);
            }

            function renderVisitors() {
                elements.content.innerHTML = renderLogTable('Visitors', state.visitors, state.visitorMeta, (entry) => `
                    <tr>
                        <td>${escapeHtml(entry.customer_name || entry.phone || '-')}</td>
                        <td>${escapeHtml(entry.customer_city || '-')}</td>
                        <td>${escapeHtml(entry.current_page || entry.entry_page || '-')}</td>
                        <td>${entry.page_views}</td>
                        <td>${entry.duration_seconds}s</td>
                        <td>${escapeHtml(entry.browser || '-')} / ${escapeHtml(entry.os || '-')}</td>
                    </tr>
                `, ['Customer', 'City', 'Page', 'Views', 'Duration', 'Device']);
            }

            function renderAnalytics() {
                elements.content.innerHTML = renderLogTable('Legacy Analytics', state.analytics, state.analyticsMeta, (entry) => `
                    <tr>
                        <td>${escapeHtml(entry.event_type)}</td>
                        <td>${escapeHtml(entry.customer_name || entry.phone || '-')}</td>
                        <td>${escapeHtml(entry.customer_city || '-')}</td>
                        <td>${escapeHtml(entry.occurred_at || '-')}</td>
                        <td>${escapeHtml(JSON.stringify(entry.event_data || {}))}</td>
                    </tr>
                `, ['Event', 'Customer', 'City', 'Occurred At', 'Data']);
            }

            function renderLogTable(title, rows, meta, rowTemplate, headings = ['Action', 'User', 'Phone', 'Time', 'Details']) {
                return `
                    <div class="admin-toolbar">
                        <div class="admin-toolbar-row">
                            <span class="admin-meta">${title}</span>
                            <span class="admin-meta">Page ${meta.current_page} / ${meta.last_page} • ${meta.total} rows</span>
                        </div>
                    </div>
                    ${rows.length ? `
                        <table class="admin-table">
                            <thead>
                                <tr>${headings.map((heading) => `<th>${heading}</th>`).join('')}</tr>
                            </thead>
                            <tbody>${rows.map(rowTemplate).join('')}</tbody>
                        </table>
                    ` : `<div class="admin-empty">No ${title.toLowerCase()} to show.</div>`}
                `;
            }

            function renderBatches() {
                elements.content.innerHTML = `
                    <div class="admin-list">
                        ${state.batches.map((batch) => `
                            <article class="admin-card compact">
                                <div class="admin-card-head">
                                    <h3 class="admin-card-title">${escapeHtml(batch.month_label)}</h3>
                                    <span class="admin-pill">${batch.products_count} products</span>
                                </div>
                                <div class="admin-meta">${batch.images_count} images • Active ${batch.active_count} • Archived ${batch.archived_count}</div>
                                ${state.isSuperAdmin ? `
                                    <div class="admin-actions">
                                        <button class="btn-secondary delete-batch-button" data-month="${escapeHtml(batch.month_key)}">Delete Month</button>
                                    </div>
                                ` : ''}
                            </article>
                        `).join('')}
                    </div>
                `;
            }

            function renderSettings() {
                const links = state.settings.group_links || [];
                const android = state.releases?.android || {};
                const ios = state.releases?.ios || {};

                elements.content.innerHTML = `
                    <div class="admin-card">
                        <div class="admin-modal-grid">
                            <div class="field full">
                                <label>Marquee speed in seconds</label>
                                <input id="marqueeSpeedInput" value="${escapeHtml(state.settings.marquee_speed_seconds)}">
                            </div>
                            <div class="field full">
                                <label>Group links</label>
                                <div id="groupLinksContainer" class="admin-list">
                                    ${links.map((link, index) => `
                                        <div class="admin-card compact">
                                            <div class="admin-modal-grid">
                                                <div class="field">
                                                    <label>Title</label>
                                                    <input data-group-label="${index}" value="${escapeHtml(link.label)}">
                                                </div>
                                                <div class="field">
                                                    <label>URL</label>
                                                    <input data-group-url="${index}" value="${escapeHtml(link.url || '')}">
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="field full">
                                <button class="btn-secondary" id="addGroupLinkButton">Add Group Link</button>
                            </div>
                            <div class="field">
                                <label>Android version</label>
                                <input id="androidVersionNameInput" value="${escapeHtml(android.version_name || '1.0.0')}">
                            </div>
                            <div class="field">
                                <label>Android version code</label>
                                <input id="androidVersionCodeInput" value="${escapeHtml(android.version_code || 1)}">
                            </div>
                            <div class="field full">
                                <label>Android notes</label>
                                <textarea id="androidNotesInput" rows="3">${escapeHtml(android.notes || '')}</textarea>
                            </div>
                            <div class="field">
                                <label>iPhone version</label>
                                <input id="iosVersionNameInput" value="${escapeHtml(ios.version_name || '')}">
                            </div>
                            <div class="field">
                                <label>iPhone build number</label>
                                <input id="iosBuildNumberInput" value="${escapeHtml(ios.build_number || '')}">
                            </div>
                            <div class="field full">
                                <label>iPhone install link</label>
                                <input id="iosExternalUrlInput" value="${escapeHtml(ios.external_url || '')}">
                            </div>
                            <div class="field full">
                                <label>iPhone notes</label>
                                <textarea id="iosNotesInput" rows="3">${escapeHtml(ios.notes || '')}</textarea>
                            </div>
                        </div>
                        <div class="admin-actions">
                            <button class="btn-primary" id="saveStorefrontSettingsButton">Save Settings</button>
                        </div>
                    </div>
                `;
            }

            function renderHealth() {
                if (!state.health) {
                    elements.content.innerHTML = '<div class="admin-empty">No system health data available yet.</div>';
                    return;
                }

                elements.content.innerHTML = `
                    <div class="admin-summary-grid">
                        ${[
                            ['Products', state.health.products],
                            ['Images', state.health.images],
                            ['Tags', state.health.tags],
                            ['Activity Logs', state.health.activity_logs],
                            ['Visitors', state.health.visitor_sessions],
                            ['Legacy Analytics', state.health.legacy_analytics_events],
                            ['Queue Jobs', state.health.queue_jobs],
                        ].map(([label, value]) => `
                            <article class="admin-summary-card">
                                <span class="admin-meta">${label}</span>
                                <strong>${value}</strong>
                            </article>
                        `).join('')}
                    </div>
                    <div class="admin-card">
                        <h3 class="admin-card-title">Exports</h3>
                        <div class="admin-inline">
                            <span class="admin-pill">Pending ${state.health.generated_exports.pending}</span>
                            <span class="admin-pill">Processing ${state.health.generated_exports.processing}</span>
                            <span class="admin-pill">Failed ${state.health.generated_exports.failed}</span>
                        </div>
                    </div>
                    <div class="admin-card">
                        <h3 class="admin-card-title">Storage</h3>
                        <div class="admin-meta">Disk: ${escapeHtml(state.health.storage.disk)}</div>
                        <div class="admin-meta">Root: ${escapeHtml(state.health.storage.root)}</div>
                        <div class="admin-meta">Product bytes: ${escapeHtml(state.health.storage.products_bytes)}</div>
                    </div>
                `;
            }

            function openModal(content) {
                elements.modal.innerHTML = content;
                elements.modalShell.hidden = false;
            }

            function closeModal() {
                elements.modalShell.hidden = true;
                elements.modal.innerHTML = '';
                state.currentProduct = null;
            }

            async function openProductEditor(productId = null) {
                try {
                    let product = null;
                    if (productId) {
                        const response = await api(`/admin/products/${productId}`);
                        product = response.data;
                    }

                    state.currentProduct = product;
                    openModal(renderProductEditorModal(product));
                } catch (error) {
                    showStatus(error.message, true);
                }
            }

            function renderProductEditorModal(product) {
                const existingImages = product?.images || [];
                const status = product?.status || (product?.is_active ? 'active' : 'active');

                return `
                    <div class="admin-modal-header">
                        <div>
                            <h2>${product ? `Edit ${escapeHtml(product.name)}` : 'New Product'}</h2>
                            <div class="admin-meta">${product?.sku ? escapeHtml(product.sku) : 'Create a fresh catalog product'}</div>
                        </div>
                        <button class="btn-secondary" id="closeAdminModalButton">Close</button>
                    </div>
                    <div class="admin-modal-grid">
                        <div class="field">
                            <label>Title</label>
                            <input id="productNameInput" value="${escapeHtml(product?.name || 'Suit')}">
                        </div>
                        <div class="field">
                            <label>Rate</label>
                            <input id="productPriceInput" value="${escapeHtml(product ? product.price : '')}" type="number" min="1" step="0.01">
                        </div>
                        <div class="field full">
                            <label>Tags (comma separated)</label>
                            <input id="productTagsInput" value="${escapeHtml((product?.tags || []).join(', '))}">
                        </div>
                        <div class="field full">
                            <label>Remarks (admin only)</label>
                            <textarea id="productRemarksInput" rows="3">${escapeHtml(product?.remarks || '')}</textarea>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select id="productStatusInput">
                                <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="archived" ${status === 'archived' ? 'selected' : ''}>Archived</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Attach up to 36 images</label>
                            <input id="productImagesInput" type="file" accept="image/*" multiple>
                        </div>
                        <div class="field full">
                            <label>Attach or replace PDF</label>
                            <input id="productPdfInput" type="file" accept="application/pdf">
                            ${product?.pdf_url ? `<div class="admin-inline"><a class="admin-link" href="${escapeHtml(product.pdf_url)}" target="_blank" rel="noopener">Open existing PDF</a><label class="admin-selection"><input type="checkbox" id="productRemovePdfInput"> Remove existing PDF</label></div>` : ''}
                        </div>
                        ${existingImages.length ? `
                            <div class="field full">
                                <label>Existing images</label>
                                <div class="admin-existing-images">
                                    ${existingImages.sort((a, b) => a.sort_order - b.sort_order).map((image) => `
                                        <article class="admin-image-card" data-image-id="${image.id}">
                                            <img src="${escapeHtml(image.medium_url || image.thumb_url || image.url)}" alt="Product image">
                                            <div class="admin-meta">${image.is_cover ? 'Cover image' : 'Gallery image'}</div>
                                            <div class="admin-image-actions">
                                                <button class="btn-secondary move-left-image-button" data-image-id="${image.id}">←</button>
                                                <button class="btn-secondary move-right-image-button" data-image-id="${image.id}">→</button>
                                                <button class="btn-secondary cover-image-button" data-image-id="${image.id}">Cover</button>
                                                <button class="btn-secondary delete-image-button" data-image-id="${image.id}">Delete</button>
                                            </div>
                                        </article>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="admin-actions">
                        <button class="btn-primary" id="saveProductButton">${product ? 'Save Product' : 'Create Product'}</button>
                        ${product && state.isSuperAdmin ? '<button class="btn-secondary" id="deleteProductButton">Delete Product</button>' : ''}
                    </div>
                `;
            }

            async function saveCurrentProduct() {
                const product = state.currentProduct;
                const payload = {
                    name: document.getElementById('productNameInput').value.trim(),
                    price: Number(document.getElementById('productPriceInput').value || 0),
                    remarks: document.getElementById('productRemarksInput').value.trim() || null,
                    status: document.getElementById('productStatusInput').value,
                    tags: document.getElementById('productTagsInput').value.split(',').map((value) => value.trim()).filter(Boolean),
                    cover_image_id: product?.images?.find((image) => image.is_cover)?.id ?? null,
                    image_order: (product?.images || []).sort((a, b) => a.sort_order - b.sort_order).map((image) => image.id),
                };

                if (!payload.name) {
                    throw new Error('Product title is required.');
                }
                if (!(payload.price > 0)) {
                    throw new Error('Rate must be greater than 0.');
                }

                const response = product
                    ? await api(`/admin/products/${product.id}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    })
                    : await api('/admin/products', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });

                const savedProduct = response.data;
                const imageInput = document.getElementById('productImagesInput');
                if (imageInput?.files?.length) {
                    if (imageInput.files.length > 36) {
                        throw new Error('A product can have at most 36 images.');
                    }
                    const formData = new FormData();
                    Array.from(imageInput.files).forEach((file) => formData.append('images[]', file));
                    formData.append('cover_index', '0');
                    await api(`/admin/products/${savedProduct.id}/images`, {
                        method: 'POST',
                        body: formData,
                    });
                }

                const pdfInput = document.getElementById('productPdfInput');
                if (document.getElementById('productRemovePdfInput')?.checked) {
                    await api(`/admin/products/${savedProduct.id}/pdf`, { method: 'DELETE' });
                }
                if (pdfInput?.files?.[0]) {
                    const pdfData = new FormData();
                    pdfData.append('pdf', pdfInput.files[0]);
                    await api(`/admin/products/${savedProduct.id}/pdf`, {
                        method: 'POST',
                        body: pdfData,
                    });
                }

                showStatus(response.message || 'Product saved.');
                closeModal();
                await loadProducts();
                await loadTags();
                renderProducts();
            }

            async function openOrderDetail(orderId, isArchived) {
                const order = (isArchived ? state.orders.archived : state.orders.active).find((entry) => entry.id === orderId);
                if (!order) return;

                openModal(`
                    <div class="admin-modal-header">
                        <div>
                            <h2>${escapeHtml(order.reference_code)}</h2>
                            <div class="admin-meta">${escapeHtml(order.customer_name)} / ${escapeHtml(order.customer_phone)}</div>
                        </div>
                        <button class="btn-secondary" id="closeAdminModalButton">Close</button>
                    </div>
                    <div class="admin-card">
                        <div class="field">
                            <label>Order status</label>
                            <select id="orderStatusInput">
                                ${['new', 'contacted', 'confirmed', 'paid', 'completed'].map((status) => `
                                    <option value="${status}" ${order.status === status ? 'selected' : ''}>${status}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="field">
                            <label>Remarks</label>
                            <textarea id="orderRemarksInput" rows="4">${escapeHtml(order.internal_notes || '')}</textarea>
                        </div>
                        <label class="admin-selection">
                            <input type="checkbox" id="orderArchiveToggle" ${order.is_archived ? 'checked' : ''}>
                            Archive this order
                        </label>
                        <div class="field">
                            <label>Ordered items</label>
                            <div class="admin-list">
                                ${(order.items || []).map((item) => `
                                    <div class="admin-card compact">
                                        <div class="admin-card-head">
                                            <strong>${escapeHtml(item.product_snapshot_name)}</strong>
                                            <span class="admin-pill">Qty ${item.quantity}</span>
                                        </div>
                                        <div class="admin-meta">${formatCurrency(item.unit_price_snapshot)}</div>
                                        ${item.product_image_url ? `<img src="${escapeHtml(item.product_image_url)}" alt="${escapeHtml(item.product_snapshot_name)}">` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="admin-actions">
                            <button class="btn-primary" id="saveOrderButton" data-order-id="${order.id}">Save Order</button>
                        </div>
                    </div>
                `);
            }

            async function openCustomerDetail(customerId) {
                const response = await api(`/admin/customers/${customerId}`);
                const customer = response.data;
                openModal(`
                    <div class="admin-modal-header">
                        <div>
                            <h2>${escapeHtml(customer.name)}</h2>
                            <div class="admin-meta">${escapeHtml(customer.phone)} / ${escapeHtml(customer.city || '-')}</div>
                        </div>
                        <button class="btn-secondary" id="closeAdminModalButton">Close</button>
                    </div>
                    <div class="admin-card">
                        <div class="admin-meta">Orders ${customer.total_orders} • Items ${customer.total_items_ordered}</div>
                        <div class="admin-meta">Last login ${escapeHtml(customer.last_login_at || '-')}</div>
                    </div>
                    <div class="admin-list">
                        ${(customer.orders || []).map((order) => `
                            <article class="admin-card compact">
                                <h3 class="admin-card-title">${escapeHtml(order.reference_code)}</h3>
                                <div class="admin-meta">${escapeHtml(order.status)}</div>
                                <div class="admin-meta">${escapeHtml(order.internal_notes || order.note || 'No remarks')}</div>
                                <div class="admin-meta">Items: ${order.items_count}</div>
                            </article>
                        `).join('')}
                    </div>
                `);
            }

            function buildSettingsPayload() {
                const labels = Array.from(elements.content.querySelectorAll('[data-group-label]'));
                const urls = Array.from(elements.content.querySelectorAll('[data-group-url]'));
                const groupLinks = labels.map((input, index) => ({
                    label: input.value.trim(),
                    url: urls[index]?.value.trim() || null,
                })).filter((link) => link.label);

                return {
                    group_links: groupLinks,
                    marquee_speed_seconds: Number(document.getElementById('marqueeSpeedInput').value || 9.6),
                };
            }

            async function saveSettings() {
                const settingsPayload = buildSettingsPayload();
                await api('/admin/settings/storefront', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settingsPayload),
                });

                if (state.isSuperAdmin) {
                    await api('/admin/settings/app-releases', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            android: {
                                version_name: document.getElementById('androidVersionNameInput').value.trim() || '1.0.0',
                                version_code: Number(document.getElementById('androidVersionCodeInput').value || 1),
                                notes: document.getElementById('androidNotesInput').value.trim() || null,
                            },
                            ios: {
                                version_name: document.getElementById('iosVersionNameInput').value.trim() || null,
                                build_number: document.getElementById('iosBuildNumberInput').value.trim() || null,
                                notes: document.getElementById('iosNotesInput').value.trim() || null,
                                external_url: document.getElementById('iosExternalUrlInput').value.trim() || null,
                            },
                        }),
                    });
                }

                showStatus('Settings saved.');
                await loadSettings();
                renderSettings();
            }

            document.addEventListener('click', async (event) => {
                const tab = event.target.closest('[data-tab]')?.dataset.tab;
                if (tab) {
                    await setTab(tab);
                    return;
                }

                if (event.target.id === 'adminMenuButton') {
                    openDrawer();
                    return;
                }

                if (event.target.id === 'closeAdminDrawerButton') {
                    closeDrawer();
                    return;
                }

                const orderMode = event.target.closest('[data-order-mode]')?.dataset.orderMode;
                if (orderMode) {
                    state.orderMode = orderMode;
                    renderOrders();
                    return;
                }

                if (event.target.id === 'productSearchButton') {
                    state.productSearch = document.getElementById('productSearchInput').value;
                    state.productIncludeArchived = document.getElementById('productArchivedToggle').checked;
                    await loadProducts();
                    renderProducts();
                    return;
                }

                if (event.target.id === 'productRefreshButton') {
                    await loadProducts();
                    renderProducts();
                    return;
                }

                if (event.target.id === 'selectionModeToggle') {
                    state.selectionMode = event.target.checked;
                    if (!state.selectionMode) {
                        state.selectedProducts = new Set();
                    }
                    renderProducts();
                    return;
                }

                const productCheckbox = event.target.closest('.product-checkbox');
                if (productCheckbox) {
                    const id = Number(productCheckbox.dataset.productId);
                    if (productCheckbox.checked) state.selectedProducts.add(id);
                    else state.selectedProducts.delete(id);
                    return;
                }

                const productEditButton = event.target.closest('.product-edit-button');
                if (productEditButton) {
                    await openProductEditor(Number(productEditButton.dataset.productId));
                    return;
                }

                if (event.target.id === 'bulkActivateButton') {
                    await api('/admin/products/bulk-status', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_ids: Array.from(state.selectedProducts), status: 'active' }),
                    });
                    showStatus('Selected products activated.');
                    state.selectedProducts = new Set();
                    state.selectionMode = false;
                    await loadProducts();
                    renderProducts();
                    return;
                }

                if (event.target.id === 'bulkArchiveButton') {
                    await api('/admin/products/bulk-status', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_ids: Array.from(state.selectedProducts), status: 'archived' }),
                    });
                    showStatus('Selected products archived.');
                    state.selectedProducts = new Set();
                    state.selectionMode = false;
                    await loadProducts();
                    renderProducts();
                    return;
                }

                if (event.target.id === 'bulkShareButton') {
                    try {
                        await shareSelectedProductsWeb();
                    } catch (error) {
                        showStatus(error.message, true);
                    }
                    return;
                }

                if (event.target.id === 'bulkDownloadButton') {
                    try {
                        await downloadSelectedProductsWeb();
                    } catch (error) {
                        showStatus(error.message, true);
                    }
                    return;
                }

                if (event.target.id === 'bulkDeleteButton') {
                    await api('/admin/products/bulk-delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_ids: Array.from(state.selectedProducts) }),
                    });
                    showStatus('Selected products deleted.');
                    state.selectedProducts = new Set();
                    state.selectionMode = false;
                    await loadProducts();
                    renderProducts();
                    return;
                }

                if (event.target.id === 'closeAdminModalButton') {
                    closeModal();
                    return;
                }

                if (event.target.id === 'saveProductButton') {
                    try {
                        await saveCurrentProduct();
                    } catch (error) {
                        showStatus(error.message, true);
                    }
                    return;
                }

                if (event.target.id === 'deleteProductButton' && state.currentProduct) {
                    if (!confirm('Delete this product and all its images?')) return;
                    await api(`/admin/products/${state.currentProduct.id}`, { method: 'DELETE' });
                    showStatus('Product deleted.');
                    closeModal();
                    await loadProducts();
                    renderProducts();
                    return;
                }

                const deleteImageButton = event.target.closest('.delete-image-button');
                if (deleteImageButton && state.currentProduct) {
                    await api(`/admin/products/${state.currentProduct.id}/images/${deleteImageButton.dataset.imageId}`, { method: 'DELETE' });
                    await openProductEditor(state.currentProduct.id);
                    showStatus('Image deleted.');
                    return;
                }

                const coverImageButton = event.target.closest('.cover-image-button');
                if (coverImageButton && state.currentProduct) {
                    const imageId = Number(coverImageButton.dataset.imageId);
                    state.currentProduct.images = state.currentProduct.images.map((image) => ({ ...image, is_cover: image.id === imageId }));
                    state.currentProduct.images = state.currentProduct.images.map((image, index) => ({ ...image, sort_order: index }));
                    openModal(renderProductEditorModal(state.currentProduct));
                    return;
                }

                const moveLeftButton = event.target.closest('.move-left-image-button');
                if (moveLeftButton && state.currentProduct) {
                    moveCurrentImage(Number(moveLeftButton.dataset.imageId), -1);
                    openModal(renderProductEditorModal(state.currentProduct));
                    return;
                }

                const moveRightButton = event.target.closest('.move-right-image-button');
                if (moveRightButton && state.currentProduct) {
                    moveCurrentImage(Number(moveRightButton.dataset.imageId), 1);
                    openModal(renderProductEditorModal(state.currentProduct));
                    return;
                }

                const orderOpenButton = event.target.closest('.order-open-button');
                if (orderOpenButton) {
                    await openOrderDetail(Number(orderOpenButton.dataset.orderId), Number(orderOpenButton.dataset.archived) === 1);
                    return;
                }

                if (event.target.id === 'saveOrderButton') {
                    const orderId = Number(event.target.dataset.orderId);
                    await api(`/admin/order-requests/${orderId}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            status: document.getElementById('orderStatusInput').value,
                            internal_notes: document.getElementById('orderRemarksInput').value.trim() || null,
                            is_archived: document.getElementById('orderArchiveToggle').checked,
                        }),
                    });
                    showStatus('Order updated.');
                    closeModal();
                    await loadOrders();
                    renderOrders();
                    return;
                }

                if (event.target.id === 'customerSearchButton') {
                    state.customerSearch = document.getElementById('customerSearchInput').value;
                    await loadCustomers();
                    renderCustomers();
                    return;
                }

                const customerOpenButton = event.target.closest('.customer-open-button');
                if (customerOpenButton) {
                    await openCustomerDetail(Number(customerOpenButton.dataset.customerId));
                    return;
                }

                if (event.target.id === 'createTagButton') {
                    const input = document.getElementById('newTagInput');
                    await api('/admin/tags', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: input.value.trim() }),
                    });
                    input.value = '';
                    showStatus('Tag created.');
                    await loadTags();
                    renderTags();
                    return;
                }

                const saveTagButton = event.target.closest('.save-tag-button');
                if (saveTagButton) {
                    const tagId = Number(saveTagButton.dataset.tagId);
                    const input = elements.content.querySelector(`[data-tag-edit-id="${tagId}"]`);
                    await api(`/admin/tags/${tagId}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: input.value.trim() }),
                    });
                    showStatus('Tag saved.');
                    await loadTags();
                    renderTags();
                    return;
                }

                const deleteTagButton = event.target.closest('.delete-tag-button');
                if (deleteTagButton) {
                    await api(`/admin/tags/${deleteTagButton.dataset.tagId}`, { method: 'DELETE' });
                    showStatus('Tag deleted.');
                    await loadTags();
                    renderTags();
                    return;
                }

                if (event.target.id === 'saveAdminButton') {
                    await api('/admin/admin-users', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            name: document.getElementById('adminNameInput').value.trim(),
                            phone: document.getElementById('adminPhoneInput').value.trim(),
                            city: document.getElementById('adminCityInput').value.trim() || null,
                            role: document.getElementById('adminRoleInput').value,
                            is_active: true,
                        }),
                    });
                    showStatus('Admin saved.');
                    await loadAdmins();
                    renderAdmins();
                    return;
                }

                const deleteAdminButton = event.target.closest('.delete-admin-button');
                if (deleteAdminButton) {
                    await api(`/admin/admin-users/${deleteAdminButton.dataset.adminId}`, { method: 'DELETE' });
                    showStatus('Admin removed.');
                    await loadAdmins();
                    renderAdmins();
                    return;
                }

                const deleteBatchButton = event.target.closest('.delete-batch-button');
                if (deleteBatchButton) {
                    if (!confirm('Delete this month and all associated products?')) return;
                    await api(`/admin/product-batches/${deleteBatchButton.dataset.month}`, { method: 'DELETE' });
                    showStatus('Batch deleted.');
                    await loadBatches();
                    renderBatches();
                    return;
                }

                if (event.target.id === 'addGroupLinkButton') {
                    state.settings.group_links.push({ label: '', url: '' });
                    renderSettings();
                    return;
                }

                if (event.target.id === 'saveStorefrontSettingsButton') {
                    await saveSettings();
                    return;
                }
            });

            function moveCurrentImage(imageId, delta) {
                if (!state.currentProduct) return;
                const images = [...state.currentProduct.images].sort((a, b) => a.sort_order - b.sort_order);
                const index = images.findIndex((image) => image.id === imageId);
                const target = index + delta;
                if (index < 0 || target < 0 || target >= images.length) return;
                [images[index], images[target]] = [images[target], images[index]];
                state.currentProduct.images = images.map((image, sortOrder) => ({ ...image, sort_order: sortOrder }));
            }

            elements.modalShell.addEventListener('click', (event) => {
                if (event.target === elements.modalShell) {
                    closeModal();
                }
            });

            elements.drawerShell.addEventListener('click', (event) => {
                if (event.target === elements.drawerShell) {
                    closeDrawer();
                }
            });

            renderTabs();
            loadCurrentTab();
        })();
    </script>
@endpush


