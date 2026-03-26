<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SCAK Product PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; }
        h1 { color: #9f3a22; }
        .item { page-break-inside: avoid; margin-bottom: 24px; border-bottom: 1px solid #ddd; padding-bottom: 16px; }
        img { width: 220px; height: 280px; object-fit: cover; border-radius: 12px; }
        .meta { margin-top: 8px; }
    </style>
</head>
<body>
    <h1>SCAK Selected Products</h1>
    @foreach($products as $product)
        <div class="item">
            <h2>{{ $product->name }}</h2>
            <p><strong>SKU:</strong> {{ $product->sku ?: 'Pending' }}</p>
            <p><strong>Price:</strong> Rs. {{ number_format((float) $product->price, 2) }}</p>
            @if($product->cover_image_url)
                <img src="{{ $product->cover_image_url }}" alt="{{ $product->name }}">
            @endif
            <div class="meta">
                <p>Supplier: {{ $product->supplier?->name ?? '-' }}</p>
                <p>City: {{ $product->city?->name ?? '-' }}</p>
                <p>Category: {{ $product->category?->name ?? '-' }}</p>
            </div>
        </div>
    @endforeach
</body>
</html>
