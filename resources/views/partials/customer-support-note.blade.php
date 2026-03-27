@php
    $noteStyle = $noteStyle ?? 'margin:14px 0 0;';
@endphp

<p class="muted" style="{{ $noteStyle }}">
    In case of any queries call / WhatsApp {{ config('scak.support.phone', '9350188297') }}.
</p>
