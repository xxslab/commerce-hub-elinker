<?php /** @var \App\Models\Company $company */ ?>
@extends('layouts.app')
@section('content')
<h1>Plan i billing</h1>

<div class="card">
    <h2>Stan konta</h2>
    @if(!$company->license_hub_workspace_id)
        <p class="muted">Konto nie jest jeszcze połączone z License Hub. Ograniczenia planu nie są egzekwowane.</p>
    @else
        <p>
            <span class="badge {{ $isActive ? 'idle' : 'error' }}">{{ $company->entitlement_status ?? 'brak danych' }}</span>
            @if($company->entitlement_plan_code) Plan: <strong>{{ $company->entitlement_plan_code }}</strong> @endif
        </p>
        <p class="muted">
            Ostatnia synchronizacja: {{ optional($company->entitlement_checked_at)->format('Y-m-d H:i:s') ?: 'nigdy' }}
            @if($company->entitlement_sync_status) ({{ $company->entitlement_sync_status }}) @endif
        </p>
        @if($company->entitlement_sync_status === 'degraded')
            <p style="color:#92400e">License Hub jest chwilowo niedostępny. Ostatni znany stan konta pozostaje w mocy — żadne dane ani dostęp nie zostały ograniczone z tego powodu.</p>
        @endif
        @if(!$gatingApplicable)
            <p class="muted">Egzekwowanie limitów planu jest obecnie wyłączone globalnie (LICENSE_HUB_ENFORCE_GATING=false).</p>
        @endif
        @if($company->entitlement_features)
            <table><tr><th>Funkcja</th><th>Wartość</th></tr>
            @foreach($company->entitlement_features as $key => $feature)
                <tr>
                    <td>{{ $key }}</td>
                    <td>
                        @if(($feature['type'] ?? null) === 'boolean')
                            {{ ($feature['enabled'] ?? false) ? 'włączone' : 'wyłączone' }}
                        @else
                            {{ $feature['usage'] ?? 0 }} / {{ ($feature['unlimited'] ?? false) ? '∞' : ($feature['limit'] ?? '-') }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </table>
        @endif
        <form method="post" action="{{ route('settings.billing.refresh') }}" style="display:inline-block;">@csrf<button class="btn">Odśwież stan konta</button></form>
    @endif
</div>

@if($errors->any())
    <div class="card" style="border-color:#b91c1c;">
        <ul style="margin:0;color:#b91c1c;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!$company->license_hub_workspace_id)
    <div class="card">
        <h2>Połącz konto z License Hub</h2>
        <p class="muted">
            Kod połączenia otrzymasz od DoSieci po założeniu subskrypcji w panelu klienta (jednorazowy, ważny przez ograniczony czas). Wklej go poniżej, aby powiązać to konto z Twoim planem — sam identyfikator workspace nie wystarcza do połączenia konta.
        </p>
        <form method="post" action="{{ route('settings.billing.connect') }}" class="filters">
            @csrf
            <label>Kod połączenia
                <input type="text" name="token" placeholder="wklej kod otrzymany od DoSieci" autocomplete="off">
            </label>
            <button class="btn">Połącz konto</button>
        </form>
    </div>
@else
    <div class="card">
        <h2>Odłącz konto od License Hub</h2>
        <p class="muted">
            Workspace: <code>{{ $company->license_hub_workspace_id }}</code>. Odłączenie nie usuwa żadnych zamówień, kanałów sprzedaży ani przesyłek — jedynie przestaje sprawdzać stan planu w License Hub, dopóki konto nie zostanie ponownie połączone nowym kodem.
        </p>
        <form method="post" action="{{ route('settings.billing.disconnect') }}" onsubmit="return confirm('Odłączyć to konto od License Hub? Dane zamówień i kanałów pozostaną nietknięte, ale limity planu przestaną być sprawdzane.');">
            @csrf
            <button class="btn" style="background:#b91c1c;border-color:#b91c1c;">Odłącz konto</button>
        </form>
    </div>
@endif

@if($auditLog->isNotEmpty())
    <div class="card">
        <h2>Historia połączenia</h2>
        <table>
            <tr><th>Zdarzenie</th><th>Workspace</th><th>Data</th></tr>
            @foreach($auditLog as $entry)
                <tr>
                    <td>{{ $entry->event }}</td>
                    <td>{{ $entry->workspace_id ?? '—' }}</td>
                    <td>{{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
@endsection
