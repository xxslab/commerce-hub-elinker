@extends('layouts.app')

@section('content')
    <h1>{{ $mode === 'create' ? 'Dodaj' : 'Edytuj' }} aplikację {{ strtoupper($marketplace) }}</h1>

    <p class="muted">
        Redirect URI wpisz dokładnie taki sam w panelu developerskim {{ strtoupper($marketplace) }}.
        Dla tej instalacji: <code>{{ url('/integrations/' . $marketplace . '/callback') }}</code>
    </p>

    <form method="POST" action="{{ $mode === 'create' ? route('marketplace-apps.store', ['marketplace' => $marketplace]) : route('marketplace-apps.update', $credential) }}">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <label>Nazwa</label>
        <input name="name" value="{{ old('name', $credential->name) }}" placeholder="np. Allegro produkcja">

        <label>Środowisko</label>
        <select name="environment">
            <option value="production" @selected(old('environment', $credential->environment) === 'production')>production</option>
            <option value="sandbox" @selected(old('environment', $credential->environment) === 'sandbox')>sandbox</option>
        </select>

        <label>Client ID</label>
        <input name="client_id" value="{{ old('client_id', $credential->client_id) }}" required>

        <label>Client Secret {{ $mode === 'edit' ? '(zostaw puste, jeśli bez zmiany)' : '' }}</label>
        <input name="client_secret" type="password" {{ $mode === 'create' ? 'required' : '' }}>

        <label>Redirect URI</label>
        <input name="redirect_uri" value="{{ old('redirect_uri', $credential->redirect_uri ?: url('/integrations/' . $marketplace . '/callback')) }}" required>

        @if($marketplace === 'ebay')
            <label>Scopes eBay</label>
            <textarea name="scopes" rows="3" placeholder="np. https://api.ebay.com/oauth/api_scope/sell.fulfillment">{{ old('scopes', $credential->scopes) }}</textarea>
        @else
            <input type="hidden" name="scopes" value="{{ old('scopes', $credential->scopes) }}">
        @endif

        <label style="display:flex;gap:8px;align-items:center;margin-top:12px;">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $credential->is_active ?? true))>
            aktywna aplikacja dla tego marketplace
        </label>

        <button class="btn" type="submit">Zapisz</button>
        <a href="{{ route('marketplace-apps.index', ['marketplace' => $marketplace]) }}">Anuluj</a>
    </form>
@endsection
