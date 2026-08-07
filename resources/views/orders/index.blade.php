@extends('layouts.app')
@section('content')
<h1>Zamówienia</h1>
<div class="card">
<form method="get" class="row-actions"><input name="q" value="{{ request('q') }}" placeholder="Szukaj numeru lub klienta"><input name="source" value="{{ request('source') }}" placeholder="woocommerce / allegro / ebay"><select name="status"><option value="">Każdy status</option>@foreach(['NEW','PAID','PROCESSING','READY_TO_SHIP','SHIPPED','COMPLETED','CANCELLED','REFUNDED','ON_HOLD','ERROR'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select><button class="btn">Filtruj</button></form>
<table><thead><tr><th>Data</th><th>Źródło</th><th>Nr</th><th>Klient</th><th>Email</th><th>Status</th><th>Kwota</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ optional($order->ordered_at)->format('Y-m-d H:i') }}</td><td>{{ optional($order->channel)->name }} / {{ $order->source }}</td><td><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></td><td>{{ $order->customer_name }}</td><td>{{ $order->maskedEmail() }}</td><td>{{ $order->status_source }} / {{ $order->status_normalized }}</td><td>{{ $order->total }} {{ $order->currency }}</td></tr>
@empty <tr><td colspan="7">Brak zamówień.</td></tr>@endforelse</tbody></table>{{ $orders->links() }}</div>
@endsection
