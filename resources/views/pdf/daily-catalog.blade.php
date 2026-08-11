<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SCAK Daily Catalog</title>
    <style>
        @page { margin: 22px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #261f1a; font-size: 11px; }
        a { color: #9f3a22; text-decoration: none; }
        .cover { text-align: center; page-break-after: always; padding: 28px 22px; }
        .logo { width: 105px; height: 105px; object-fit: contain; margin-bottom: 8px; }
        .brand { color: #9f3a22; font-size: 30px; margin: 0 0 4px; }
        .subtitle { font-size: 17px; margin: 0 0 22px; }
        .index { width: 100%; border-collapse: separate; border-spacing: 0 9px; margin: 22px 0; }
        .index td { background: #f2e7d8; border-radius: 10px; padding: 13px; font-size: 14px; }
        .contact { margin-top: 26px; padding: 16px; border: 1px solid #d8c5ad; border-radius: 12px; line-height: 1.7; }
        .page { page-break-after: always; position: relative; height: 760px; }
        .page.last-page { page-break-after: auto; }
        .section-title { background: #2f241d; color: white; padding: 10px 13px; border-radius: 9px; margin-bottom: 10px; font-size: 16px; }
        .grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .grid td { width: 50%; vertical-align: top; padding: 6px; }
        .card { border: 1px solid #dfd2c4; border-radius: 10px; overflow: hidden; height: 305px; }
        .card img { display: block; width: 100%; height: 228px; object-fit: cover; }
        .details { padding: 7px 9px; }
        .name { font-size: 12px; font-weight: bold; height: 28px; overflow: hidden; }
        .price { color: #9f3a22; font-size: 15px; font-weight: bold; margin-top: 4px; }
        .sku { color: #6d5842; font-size: 9px; }
        .footer { position: absolute; left: 6px; right: 6px; bottom: 0; border-top: 1px solid #dfd2c4; padding-top: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="cover" id="catalog-home">
        @if($logoData)<img class="logo" src="{{ $logoData }}">@endif
        <h1 class="brand">SCAK MART</h1>
        <p class="subtitle">Daily Wholesale Catalog</p>
        <p>Generated {{ $generatedAt->format('d M Y, h:i A') }} | {{ $productsCount }} recent products</p>
        <table class="index">
            @foreach($sections as $section)
                <tr><td><a href="#section-{{ $section['key'] }}">{{ $section['label'] }} ({{ $section['products']->count() }} products)</a></td></tr>
            @endforeach
        </table>
        <div class="contact">
            <strong>Visit or contact SCAK</strong><br>
            @if(filled($settings['address'])){{ $settings['address'] }}<br>@endif
            Phone / WhatsApp: {{ $settings['phone'] }}<br>
            @if(filled($settings['shop_hours'])){{ $settings['shop_hours'] }}<br>@endif
            @if(filled($settings['location_url']))<a href="{{ $settings['location_url'] }}">Open shop location</a>@endif
        </div>
    </div>

    @php($finalSectionIndex = $sections->count() - 1)
    @foreach($sections as $sectionIndex => $section)
        @foreach($section['products']->chunk(4) as $pageIndex => $products)
            @php($isLastPage = $sectionIndex === $finalSectionIndex && $pageIndex === $section['products']->chunk(4)->count() - 1)
            <div class="page{{ $isLastPage ? ' last-page' : '' }}" @if($pageIndex === 0) id="section-{{ $section['key'] }}" @endif>
                <div class="section-title">{{ $section['label'] }}</div>
                <table class="grid">
                    @foreach($products->chunk(2) as $row)
                        <tr>
                            @foreach($row as $product)
                                <td>
                                    <div class="card">
                                        <a href="{{ $product['url'] }}"><img src="{{ $product['image_data'] }}"></a>
                                        <div class="details">
                                            <div class="name">{{ $product['name'] }}</div>
                                            <div class="price">Price: Rs.{{ rtrim(rtrim(number_format($product['price'], 2, '.', ''), '0'), '.') }}</div>
                                            <div class="sku">{{ $product['sku'] }}</div>
                                        </div>
                                    </div>
                                </td>
                            @endforeach
                            @if($row->count() === 1)<td></td>@endif
                        </tr>
                    @endforeach
                </table>
                <div class="footer"><a href="#catalog-home">Go back to price index</a> | {{ $settings['phone'] }}</div>
            </div>
        @endforeach
    @endforeach
</body>
</html>
