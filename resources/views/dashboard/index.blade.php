@extends('layouts.app')
@section('content')
<h1>Commerce Hub</h1>

<h2>Sprzedaż</h2>
<div class="grid">
    <div class="card"><div class="muted">Zamówienia dzisiaj</div><div class="big">{{ $ordersTodayCount }}</div></div>
    <div class="card"><div class="muted">Nowe zamówienia</div><div class="big">{{ $ordersNewCount }}</div></div>
    <div class="card"><div class="muted">Do wysyłki</div><div class="big">{{ $ordersReadyToShipCount }}</div></div>
    <div class="card"><div class="muted">Wysłane</div><div class="big">{{ $ordersShippedCount }}</div></div>
</div>

<h2>Integracje</h2>
<div class="grid">
    <div class="card"><div class="muted">Kanały sprzedaży</div><div class="big">{{ $channels->count() }}</div></div>
    <div class="card"><div class="muted">Synchronizuje</div><div class="big">{{ $channels->where('sync_status','syncing')->count() }}</div></div>
    <div class="card"><div class="muted">Błędy synchronizacji</div><div class="big">{{ $channels->where('sync_status','error')->count() + $channels->where('sync_status','authentication_error')->count() }}</div></div>
    <div class="card"><div class="muted">Wyłączone</div><div class="big">{{ $channels->where('is_enabled', false)->count() }}</div></div>
</div>
<div class="card">
<table>
<thead><tr><th>Status</th><th>Nazwa</th><th>Typ</th><th>URL</th><th>Ostatni sync</th><th>Ilość</th><th>Błąd</th><th>Akcja</th></tr></thead>
<tbody>
@forelse($channels as $channel)
@php($status = $channel->sync_status ?: 'idle')
<tr>
<td><span class="badge {{ $status }}"><span class="dot {{ $status }}"></span>{{ $status }}</span></td>
<td>{{ $channel->name }}</td>
<td><span class="source-badge source-{{ in_array($channel->type,['woocommerce','allegro','ebay'],true) ? $channel->type : 'other' }}">{{ ['woocommerce'=>'WooCommerce','allegro'=>'Allegro','ebay'=>'eBay'][$channel->type] ?? $channel->type }}</span></td>
<td>{{ $channel->base_url ?? $channel->url }}</td>
<td>{{ optional($channel->last_sync_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
<td>{{ $channel->last_sync_count ?? 0 }}</td>
<td class="err" title="{{ $channel->last_error }}">{{ $channel->last_error }}</td>
<td><form method="POST" action="{{ route('channels.sync',$channel) }}">@csrf<button class="btn">Sync</button></form></td>
</tr>
@empty
<tr><td colspan="8">Brak integracji. Dodaj WooCommerce w ekranie Kanały.</td></tr>
@endforelse
</tbody>
</table>
</div>

<h2>InPost</h2>
<div class="grid">
    <div class="card"><div class="muted">Przesyłki utworzone</div><div class="big">{{ $shipmentsCount }}</div></div>
    <div class="card"><div class="muted">Błędy</div><div class="big">{{ $shipmentsErrorCount }}</div></div>
</div>

<h2>Konto</h2>
<div class="card">
    @if(!$company->license_hub_workspace_id)
        <p class="muted">Konto nie jest powiązane z License Hub. <a href="{{ route('settings.billing') }}">Powiąż w Ustawieniach</a>.</p>
    @else
        <p>
            <span class="badge {{ $isActive ? 'idle' : 'error' }}">{{ $company->entitlement_status ?? 'brak danych' }}</span>
            @if($company->entitlement_plan_code) Plan: <strong>{{ $company->entitlement_plan_code }}</strong> @endif
            @if(!$isActive && $gatingApplicable)
                <span style="color:#991b1b">— część funkcji jest ograniczona. Zobacz <a href="{{ route('settings.billing') }}">Plan i billing</a>.</span>
            @endif
        </p>
    @endif
</div>
@endsection
