@extends('layouts.app')

@section('content')
    <h1>Ustawienia marketplace OAuth</h1>
    <p class="muted">Tu wpisujesz dane aplikacji developerskiej. Klient potem tylko klika „Połącz Allegro/eBay” i autoryzuje swoje konto sprzedawcy.</p>

    <p>
        <a class="btn" href="{{ route('marketplace-apps.create', ['marketplace' => 'allegro']) }}">Dodaj aplikację Allegro</a>
        <a class="btn" href="{{ route('marketplace-apps.create', ['marketplace' => 'ebay']) }}">Dodaj aplikację eBay</a>
    </p>

    <table>
        <thead>
        <tr>
            <th>Marketplace</th>
            <th>Nazwa</th>
            <th>Środowisko</th>
            <th>Client ID</th>
            <th>Redirect URI</th>
            <th>Aktywna</th>
            <th>Akcje</th>
        </tr>
        </thead>
        <tbody>
        @forelse($credentials as $credential)
            <tr>
                <td>{{ strtoupper($credential->marketplace) }}</td>
                <td>{{ $credential->name }}</td>
                <td>{{ $credential->environment }}</td>
                <td><code>{{ \Illuminate\Support\Str::limit($credential->client_id, 32) }}</code></td>
                <td><code>{{ $credential->redirect_uri }}</code></td>
                <td>{{ $credential->is_active ? 'tak' : 'nie' }}</td>
                <td><a href="{{ route('marketplace-apps.edit', $credential) }}">Edytuj</a></td>
            </tr>
        @empty
            <tr><td colspan="7">Brak danych aplikacji. Dodaj Allegro/eBay, żeby uruchomić OAuth.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
