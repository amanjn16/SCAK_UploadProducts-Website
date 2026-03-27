@php
    $nameId = $nameId ?? 'customerName';
    $phoneId = $phoneId ?? 'customerPhone';
    $cityId = $cityId ?? 'customerCity';
    $cityPlaceholder = $cityPlaceholder ?? config('scak.support.default_city', 'Delhi');
    $withTopMargin = $withTopMargin ?? false;
@endphp

<div class="field" @if($withTopMargin) style="margin-top:14px;" @endif>
    <label>Name</label>
    <input id="{{ $nameId }}" placeholder="Enter your name">
</div>
<div class="field">
    <label>Phone</label>
    <input id="{{ $phoneId }}" placeholder="{{ config('scak.support.phone', '9350188297') }}">
</div>
<div class="field">
    <label>City</label>
    <input id="{{ $cityId }}" placeholder="{{ $cityPlaceholder }}">
</div>
