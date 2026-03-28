@extends('layouts.app', ['title' => $product->name])

@section('content')
    <style>
        .product-detail-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
            align-items: start;
        }
        .product-detail-main img,
        .product-detail-gallery img {
            display: block;
            width: 100%;
        }
        .product-lightbox {
            position: fixed;
            inset: 0;
            background: rgba(15, 15, 15, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 50;
        }
        .product-lightbox.open {
            display: flex;
        }
        .product-lightbox img {
            max-width: min(96vw, 1100px);
            max-height: 88vh;
            border-radius: 18px;
            object-fit: contain;
            background: #111;
        }
        .product-lightbox button {
            position: absolute;
            top: 18px;
            right: 18px;
        }
        .product-detail-gallery {
            margin-top: 18px;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
        @media (max-width: 900px) {
            .product-detail-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <section class="product-detail-layout">
        <div class="panel product-detail-main" style="padding: 24px;">
            <button class="btn-secondary back-link" type="button" onclick="window.scakCatalogState.goBack('{{ route('catalog') }}')">&#8592; Go Back</button>
            <img
                src="{{ $product->cover_image_url ?? 'https://placehold.co/900x1125?text=SCAK' }}"
                alt="{{ $product->name }}"
                data-lightbox-src="{{ $product->cover_image_original_url ?? $product->cover_image_url }}"
                style="width: 100%; border-radius: 24px; aspect-ratio: 4 / 5; object-fit: cover; cursor: zoom-in;"
            >
            @if($product->images->count() > 1)
                <div class="product-grid product-detail-gallery">
                    @foreach($product->images as $image)
                        <img
                            src="{{ $image->thumb_url ?: $image->url }}"
                            alt="{{ $product->name }}"
                            data-lightbox-src="{{ $image->url }}"
                            style="border-radius: 18px; aspect-ratio: 1 / 1; object-fit: cover; cursor: zoom-in;"
                        >
                    @endforeach
                </div>
            @endif
        </div>
        <aside class="panel" style="padding: 24px;">
            <span class="pill">{{ $product->is_active ? 'Active product' : 'Archived product' }}</span>
            <h1>{{ $product->name }}</h1>
            <p class="muted">SKU: {{ $product->sku ?: 'Will be assigned' }}</p>
            <p style="font-size: 1.45rem;"><strong>Rs. {{ number_format((float) $product->price, 2) }}</strong></p>
            <p>{{ $product->description ?: 'This product is available for offline order confirmation through the SCAK sales team.' }}</p>
            @if($product->pdf_url)
                <div style="display: grid; gap: 10px; margin-bottom: 16px;">
                    <a class="btn-secondary" href="{{ $product->pdf_url }}" target="_blank" rel="noopener">View PDF</a>
                    <a class="btn-secondary" href="{{ $product->pdf_url }}?download=1">Download PDF</a>
                </div>
            @endif
            <div class="tag-list">
                @forelse($product->tags as $tag)
                    <div class="pill">{{ $tag->name }}</div>
                @empty
                    <div class="pill">No tags</div>
                @endforelse
            </div>
            @if($product->is_active)
                <button class="btn-primary" style="width: 100%; margin-top: 18px;" onclick="window.scakCart.add({{ $product->id }}); window.scakCatalogState.save(window.scakCatalogState.read() || { page: 1, scrollY: 0 }); window.location.href='{{ route('bucket') }}';">Add to Cart</button>
            @else
                <button class="btn-secondary" style="width: 100%; margin-top: 18px;" disabled>Archived items are view only</button>
            @endif
        </aside>
    </section>
    <div class="product-lightbox" id="productLightbox" aria-hidden="true">
        <button class="btn-secondary" type="button" id="productLightboxClose">Close</button>
        <img src="" alt="{{ $product->name }}" id="productLightboxImage">
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const lightbox = document.getElementById('productLightbox');
        const lightboxImage = document.getElementById('productLightboxImage');
        const closeButton = document.getElementById('productLightboxClose');
        const triggers = document.querySelectorAll('[data-lightbox-src]');

        if (!lightbox || !lightboxImage || triggers.length === 0) {
            return;
        }

        const closeLightbox = () => {
            lightbox.classList.remove('open');
            lightbox.setAttribute('aria-hidden', 'true');
            lightboxImage.src = '';
        };

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const src = trigger.getAttribute('data-lightbox-src');
                if (!src) {
                    return;
                }

                lightboxImage.src = src;
                lightbox.classList.add('open');
                lightbox.setAttribute('aria-hidden', 'false');
            });
        });

        closeButton?.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });
    })();
</script>
@endpush
