@extends('layouts.app')

@php($title = 'Admin Apps')

@section('content')
<div class="grid" style="gap: 20px;">
    <section class="panel" style="padding: 24px;">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <p class="muted" style="margin:0 0 8px;">Internal Downloads</p>
                <h1 style="margin:0 0 10px; font-size:clamp(1.8rem, 4vw, 2.6rem);">Admin App Builds</h1>
                <p class="muted" style="margin:0; max-width:42rem;">Download the latest internal Android build here. iPhone distribution will appear here as soon as the iOS build is provisioned.</p>
            </div>
            <a href="{{ route('catalog') }}" class="btn btn-secondary">Back To Catalog</a>
        </div>
    </section>

    <section class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;">
        @foreach ($releases as $release)
            <article class="panel" style="padding: 22px;">
                <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start;">
                    <div>
                        <p class="muted" style="margin:0 0 8px;">{{ strtoupper($release['platform']) }}</p>
                        <h2 style="margin:0 0 8px; font-size:1.25rem;">{{ $release['title'] }}</h2>
                        @if (!empty($release['version_name']))
                            <p style="margin:0 0 8px;"><strong>Version:</strong> {{ $release['version_name'] }}@if(!empty($release['version_code'])) ({{ $release['version_code'] }})@endif @if(!empty($release['build_number'])) (Build {{ $release['build_number'] }})@endif</p>
                        @endif
                    </div>
                    <span class="pill">{{ $release['available'] ? 'Available' : 'Pending' }}</span>
                </div>
                <p class="muted" style="margin:12px 0 18px;">{{ $release['notes'] }}</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    @if (!empty($release['download_url']))
                        <a href="{{ $release['download_url'] }}" class="btn btn-primary">Download</a>
                    @endif
                    @if (!empty($release['external_url']))
                        <a href="{{ $release['external_url'] }}" class="btn btn-secondary" target="_blank" rel="noreferrer">Open Link</a>
                    @endif
                    @if (empty($release['download_url']) && empty($release['external_url']))
                        <span class="pill">Coming Soon</span>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection
