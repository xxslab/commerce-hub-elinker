@extends('layouts.app')
@section('content')
<h1>Integracje</h1>
<div class="card"><h2>Dodaj kanał sprzedaży</h2><p class="muted">WooCommerce dodajesz kluczami API. Allegro i eBay łączysz przez OAuth.</p><p><a class="btn" href="{{ route('channels.woocommerce.create') }}">Dodaj WooCommerce</a> <a class="btn btn2" href="{{ route('integrations.allegro.connect') }}">Połącz Allegro</a> <a class="btn btn2" href="{{ route('integrations.ebay.connect') }}">Połącz eBay</a></p></div>
<div class="card"><table><tr><th>Nazwa</th><th>Typ</th><th>URL/konto</th><th>Status</th><th>Ostatnia synchronizacja</th><th>Akcje</th></tr>
@forelse($channels as $channel)<tr><td>{{ $channel->name }}</td><td><span class="badge">{{ $channel->type }}</span></td><td>{{ $channel->base_url ?: 'OAuth marketplace' }}</td><td>{{ $channel->sync_status ?: $channel->status }} @if(!$channel->is_enabled)<span class="muted">(wyłączony)</span>@endif</td><td>{{ $channel->last_orders_sync_at ?: '-' }}</td><td class="row-actions"><form method="post" action="{{ route('channels.test', $channel) }}">@csrf<button class="btn btn2">Test połączenia</button></form>@if(in_array($channel->type, ['allegro','ebay'], true))<form method="post" action="{{ route('channels.refreshToken', $channel) }}">@csrf<button class="btn btn2">Odśwież token</button></form>@endif<form method="post" action="{{ route('channels.toggle', $channel) }}">@csrf<button class="btn">{{ $channel->is_enabled ? 'Zatrzymaj sync' : 'Włącz sync' }}</button></form><form method="post" action="{{ route('channels.sync', $channel) }}">@csrf<button class="btn">Synchronizuj</button></form><form method="post" action="{{ route('channels.destroy', $channel) }}" onsubmit="return confirm('Usunąć kanał i jego lokalne zamówienia?')">@csrf @method('DELETE')<button class="btn" style="background:#b91c1c">Usuń</button></form></td></tr>
@if($channel->type === 'woocommerce' && $channel->getWebhookSecret())
<tr><td colspan="6" class="muted" style="font-size:12px">
    Webhook URL (WooCommerce → Ustawienia → Zaawansowane → Webhooki, temat: dowolny "Zamówienie"):
    <code>{{ url('/api/webhooks/woocommerce/' . $channel->id) }}</code>
    · Secret: <code>{{ $channel->getWebhookSecret() }}</code>
</td></tr>
@endif
@empty<tr><td colspan="6">Brak integracji.</td></tr>@endforelse</table></div>{{ $channels->links() }}
@endsection
