@extends('layouts.app')
@section('content')
<h1>Zamówienia</h1>
<div class="card">
<form method="get" class="filters">
    <label>Szukaj
        <input name="q" value="{{ request('q') }}" placeholder="Nr / klient / e-mail / external ID">
    </label>
    <label>Źródło
        <select name="source">
            <option value="">Wszystkie</option>
            <option value="woocommerce" @selected(request('source') === 'woocommerce')>WooCommerce</option>
            <option value="allegro" @selected(request('source') === 'allegro')>Allegro</option>
            <option value="ebay" @selected(request('source') === 'ebay')>eBay</option>
        </select>
    </label>
    <label>Kanał
        <select name="channel_id">
            <option value="">Wszystkie</option>
            @foreach($channels as $channel)
                <option value="{{ $channel->id }}" @selected((string) request('channel_id') === (string) $channel->id)>{{ $channel->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Status realizacji
        <select name="status">
            <option value="">Każdy</option>
            @foreach(\App\Services\Orders\OrderStatusMapper::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </label>
    <label>Status płatności
        <select name="payment_status">
            <option value="">Każdy</option>
            @foreach(['paid','unknown','pending','refunded'] as $ps)
                <option value="{{ $ps }}" @selected(request('payment_status') === $ps)>{{ $ps }}</option>
            @endforeach
        </select>
    </label>
    <label>Kraj
        <input name="country" value="{{ request('country') }}" placeholder="PL" maxlength="2" style="width:60px">
    </label>
    <label>Waluta
        <input name="currency" value="{{ request('currency') }}" placeholder="PLN" maxlength="3" style="width:70px">
    </label>
    <label>Od
        <input type="date" name="from" value="{{ request('from') }}">
    </label>
    <label>Do
        <input type="date" name="to" value="{{ request('to') }}">
    </label>
    <button class="btn">Filtruj</button>
    <a class="btn btn2" href="{{ route('orders.index') }}">Wyczyść</a>
</form>
</div>
<div class="card table-scroll">
<table><thead><tr>
    <th>Data</th>
    <th>Domena źródłowa</th>
    <th>Nazwa kanału</th>
    <th>Nr zamówienia</th>
    <th>Klient</th>
    <th>Kraj</th>
    <th>Kwota</th>
    <th>Status realizacji</th>
    <th>Płatność</th>
    <th>Przesyłka</th>
    <th>Tracking</th>
</tr></thead><tbody>
@forelse($orders as $order)
    @php($shipment = $order->shipments->first())
    <tr>
        <td>{{ optional($order->ordered_at)->format('Y-m-d H:i') }}</td>
        <td>
            @php($sourceClass = in_array($order->source, ['woocommerce','allegro','ebay'], true) ? $order->source : 'other')
            <span class="source-badge source-{{ $sourceClass }}">
                {{ $order->salesChannel?->display_domain ?? ucfirst($order->source) }}
            </span>
        </td>
        <td>
            @if($order->salesChannel)
                <a href="{{ route('channels.edit', $order->salesChannel) }}">{{ $order->salesChannel->name }}</a>
            @else
                -
            @endif
        </td>
        <td><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></td>
        <td>{{ $order->customer_name }}<br><span class="muted">{{ $order->maskedEmail() }}</span></td>
        <td>{{ $order->shipping_country ?: $order->billing_country ?: '-' }}</td>
        <td>{{ number_format((float) $order->total, 2) }} {{ $order->currency }}</td>
        <td><span class="badge">{{ $order->status_normalized }}</span></td>
        <td><span class="pay-{{ in_array($order->payment_status, ['paid','unknown','pending'], true) ? $order->payment_status : 'unknown' }}">{{ $order->payment_status ?: '-' }}</span></td>
        <td>{{ $shipment ? $shipment->status : '-' }}</td>
        <td>{{ $shipment->tracking_number ?? '-' }}</td>
    </tr>
@empty
    <tr><td colspan="11">Brak zamówień dla wybranych filtrów.</td></tr>
@endforelse
</tbody></table>
</div>
{{ $orders->links() }}
@endsection
