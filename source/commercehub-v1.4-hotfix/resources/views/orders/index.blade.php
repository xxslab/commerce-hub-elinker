@extends('layouts.app')
@section('content')
<h1>Zamówienia</h1>
<div class="card">
<table>
<thead><tr><th>Data</th><th>Źródło</th><th>Nr</th><th>Klient</th><th>Email</th><th>Status</th><th>Kwota</th></tr></thead>
<tbody>
@forelse($orders as $order)
<tr>
<td>{{ optional($order->ordered_at)->format('Y-m-d H:i') }}</td>
<td>{{ optional($order->channel)->name }} / {{ $order->source }}</td>
<td>{{ $order->order_number }}</td>
<td>{{ $order->customer_name }}</td>
<td>{{ $order->customer_email }}</td>
<td>{{ $order->status_source }} / {{ $order->status_normalized }}</td>
<td>{{ $order->total }} {{ $order->currency }}</td>
</tr>
@empty
<tr><td colspan="7">Brak zamówień.</td></tr>
@endforelse
</tbody>
</table>
{{ $orders->links() }}
</div>
@endsection
