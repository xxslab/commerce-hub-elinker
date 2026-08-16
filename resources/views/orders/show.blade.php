@extends('layouts.app')
@section('content')
<h1>Zamówienie {{ $order->order_number }}</h1><p><a href="{{ route('orders.index') }}">← wróć do listy</a></p>
<div class="grid"><div class="card"><h2>Źródło</h2><p>{{ $order->salesChannel?->name }}<br>{{ $order->source }}<br>{{ $order->external_order_id }}</p></div><div class="card"><h2>Status</h2><p><span class="badge">{{ $order->status_normalized }}</span><br>Źródło: {{ $order->status_source }}</p></div><div class="card"><h2>Kwota</h2><p>{{ $order->total }} {{ $order->currency }}</p></div></div>
<div class="card"><h2>Zmień status lokalny</h2><form method="post" action="{{ route('orders.status', $order) }}">@csrf @method('PATCH')<select name="status_normalized">@foreach(['NEW','PAID','PROCESSING','READY_TO_SHIP','SHIPPED','COMPLETED','CANCELLED','REFUNDED','ON_HOLD','ERROR'] as $status)<option value="{{ $status }}" @selected($order->status_normalized === $status)>{{ $status }}</option>@endforeach</select><button class="btn">Zapisz lokalnie</button></form></div>
<div class="card"><h2>Klient</h2><p>{{ $order->customer_name }}<br>{{ $order->maskedEmail() }}<br>{{ $order->maskedPhone() }}</p><h3>Adres dostawy</h3><div class="code">{{ json_encode($order->shipping_address, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</div></div>
<div class="card"><h2>Produkty</h2><table><tr><th>SKU</th><th>Nazwa</th><th>Ilość</th><th>Cena</th><th>Razem</th></tr>@foreach($order->items as $item)<tr><td>{{ $item->sku }}</td><td>{{ $item->name }}</td><td>{{ $item->quantity }}</td><td>{{ $item->price }}</td><td>{{ $item->total }}</td></tr>@endforeach</table></div>
<div class="card"><h2>Historia statusów</h2><table><tr><th>Data</th><th>Zmiana</th><th>Źródło</th><th>Synchronizacja</th></tr>@forelse($order->statusHistory as $history)<tr><td>{{ $history->created_at }}</td><td>{{ $history->from_status }} → {{ $history->to_status }}</td><td>{{ $history->source }}</td><td>{{ $history->sync_status }}</td></tr>@empty<tr><td colspan="4">Brak historii.</td></tr>@endforelse</table></div>

<div class="card">
    <h2>Przesyłka</h2>
    @forelse($order->shipments as $shipment)
        <div class="card" style="margin:8px 0;background:#f9fafb">
            <p>
                <span class="badge">{{ strtoupper($shipment->carrier) }}</span>
                <span class="badge">{{ $shipment->status }}</span>
                @if($shipment->tracking_number) Tracking: <strong>{{ $shipment->tracking_number }}</strong> @endif
            </p>
            <p class="row-actions">
                @if($shipment->label_path)
                    <a class="btn btn2" href="{{ route('shipments.label', $shipment) }}">Pobierz etykietę</a>
                @endif
                <form method="post" action="{{ route('shipments.refreshTracking', $shipment) }}">@csrf<button class="btn btn2">Odśwież tracking</button></form>
                <form method="post" action="{{ route('shipments.pushTracking', $shipment) }}">@csrf<button class="btn btn2">Wyślij tracking do źródła</button></form>
            </p>
            @if($shipment->events->count())
                <table><tr><th>Data</th><th>Status</th></tr>
                @foreach($shipment->events as $event)
                    <tr><td>{{ optional($event->occurred_at)->format('Y-m-d H:i') }}</td><td>{{ $event->status }}</td></tr>
                @endforeach
                </table>
            @endif
        </div>
    @empty
        <p class="muted">Brak przesyłek dla tego zamówienia.</p>
    @endforelse

    @if(!$order->shipments->whereNotIn('status', ['CANCELLED', 'ERROR'])->count())
        <form method="post" action="{{ route('shipments.inpost.create', $order) }}" class="filters">
            @csrf
            <label>Gabaryt
                <select name="template">
                    <option value="small">Mały</option>
                    <option value="medium">Średni</option>
                    <option value="large">Duży</option>
                </select>
            </label>
            <label>Waga (kg)
                <input type="number" step="0.1" min="0.1" name="weight" value="1">
            </label>
            <label>Usługa
                <select name="service">
                    <option value="inpost_courier_standard">Kurier InPost</option>
                    <option value="inpost_locker_standard">Paczkomat</option>
                </select>
            </label>
            <label>Kod Paczkomatu (jeśli dotyczy)
                <input type="text" name="point" placeholder="np. WAW01A" value="{{ old('point') }}">
            </label>
            <button class="btn">Utwórz przesyłkę InPost</button>
        </form>
    @endif
</div>

<div class="card"><h2>Bezpieczny podgląd integracji</h2><div class="code">{{ json_encode($order->safeIntegrationPayload(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</div></div>
@endsection
