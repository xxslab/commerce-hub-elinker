@extends('layouts.app')
@section('content')
<h1>Commerce Hub</h1>
<div class="grid">
    <div class="card"><div class="muted">Kanały sprzedaży</div><div class="big">{{ $channels->count() }}</div></div>
    <div class="card"><div class="muted">Zamówienia w bazie</div><div class="big">{{ $ordersCount }}</div></div>
    <div class="card"><div class="muted">Synchronizuje</div><div class="big">{{ $channels->where('sync_status','syncing')->count() }}</div></div>
    <div class="card"><div class="muted">Błędy</div><div class="big">{{ $channels->where('sync_status','error')->count() }}</div></div>
</div>
<div class="card">
<h2>Integracje</h2>
<table>
<thead><tr><th>Status</th><th>Nazwa</th><th>Typ</th><th>URL</th><th>Ostatni sync</th><th>Ilość</th><th>Błąd</th><th>Akcja</th></tr></thead>
<tbody>
@forelse($channels as $channel)
@php($status = $channel->sync_status ?: 'idle')
<tr>
<td><span class="badge {{ $status }}"><span class="dot {{ $status }}"></span>{{ $status }}</span></td>
<td>{{ $channel->name }}</td>
<td>{{ $channel->type }}</td>
<td>{{ $channel->base_url ?? $channel->url }}</td>
<td>{{ optional($channel->last_sync_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
<td>{{ $channel->last_sync_count ?? 0 }}</td>
<td class="err" title="{{ $channel->last_error }}">{{ $channel->last_error }}</td>
<td><form method="POST" action="{{ route('channels.sync',$channel) }}">@csrf<button class="btn">Sync</button></form></td>
</tr>
@empty
<tr><td colspan="8">Brak integracji. Dodaj WooCommerce w dotychczasowym ekranie integracji.</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection
