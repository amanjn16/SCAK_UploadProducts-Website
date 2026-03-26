@extends('layouts.app', ['title' => $product->name])

@section('content')
    <section class="grid" style="grid-template-columns: minmax(0, 1fr) 360px; align-items: start;">
        <div class="panel" style="padding: 24px;">
            <img src="{{ $product->cover_image_url ?? 'https://placehold.co/900x1125?text=SCAK' }}" alt="{{ $product->name }}" style="width: 100%; border-radius: 24px; aspect-ratio: 4 / 5; object-fit: cover;">
            @if($product->images->count() > 1)
                <div class="product-grid" style="margin-top: 18px; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
                    @foreach($product->images as $image)
                        <img src="{{ $image->url }}" alt="{{ $product->name }}" style="border-radius: 18px; aspect-ratio: 1 / 1; object-fit: cover;">
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
            <div class="tag-list">
                @forelse($product->tags as $tag)
                    <div class="pill">{{ $tag->name }}</div>
                @empty
                    <div class="pill">No tags</div>
                @endforelse
            </div>
            @if($product->is_active)
                <button class="btn-primary" style="width: 100%; margin-top: 18px;" onclick="window.scakCart.add({{ $product->id }}); window.location.href='{{ route('bucket') }}';">Add to Cart</button>
            @else
                <button class="btn-secondary" style="width: 100%; margin-top: 18px;" disabled>Archived items are view only</button>
            @endif
        </aside>
    </section>
@endsection
