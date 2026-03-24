@php
    $branding = app(\App\Services\Branding\AppBrandingService::class)->branding();
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-flex; align-items: center; justify-content: center;">
@if ($branding['logo_url'])
<img src="{{ $branding['logo_url'] }}" class="logo" alt="{{ config('app.name') }}" style="max-height: 72px; width: auto;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
