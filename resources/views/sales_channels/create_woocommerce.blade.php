@extends('layouts.app')

@section('content')
<h1>Dodaj sklep WooCommerce</h1>
<div class="card">
<form method="post" action="{{ route('channels.woocommerce.store') }}">
    @csrf
    <label>Nazwa integracji</label>
    <input name="name" value="{{ old('name', 'Sklep WooCommerce') }}" required>
    @error('name')<p class="error">{{ $message }}</p>@enderror

    <label>URL sklepu</label>
    <input name="base_url" placeholder="https://sklep.pl" value="{{ old('base_url') }}" required>
    @error('base_url')<p class="error">{{ $message }}</p>@enderror

    <label>Consumer Key</label>
    <input name="consumer_key" value="{{ old('consumer_key') }}" required>
    @error('consumer_key')<p class="error">{{ $message }}</p>@enderror

    <label>Consumer Secret</label>
    <input name="consumer_secret" value="{{ old('consumer_secret') }}" required>
    @error('consumer_secret')<p class="error">{{ $message }}</p>@enderror

    <p class="muted">W WooCommerce: Ustawienia → Zaawansowane → REST API → Dodaj klucz. Daj uprawnienia Read/Write.</p>
    <button class="btn">Zapisz</button>
</form>
</div>
@endsection
