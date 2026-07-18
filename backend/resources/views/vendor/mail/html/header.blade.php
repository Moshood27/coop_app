@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php($brandSlug = config('brand.slug', 'attaqwa'))
<img src="{{ config('app.url') . '/images/' . $brandSlug . '-logo.svg' }}" class="logo" alt="{{ config('app.name') }}" height="50" style="height: 50px;">
</a>
<div style="margin-top: 10px; font-size: 18px; font-weight: bold; color: #374151;">{{ config('app.name') }}</div>
</td>
</tr>
