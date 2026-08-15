<?php /** @var \App\Models\Company $company */ ?>
@extends('layouts.app')
@section('content')
<h1>Plan i billing</h1>

<div class="card">
    <h2>Stan konta</h2>
    @if(!$company->license_hub_workspace_id)
        <p class="muted">Konto nie jest jeszcze powiązane z License Hub. Ograniczenia planu nie są egzekwowane.</p>
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
        <form method="post" action="{{ route('settings.billing.refresh') }}">@csrf<button class="btn">Odśwież stan konta</button></form>
    @endif
</div>

<div class="card">
    <h2>Powiąż workspace License Hub</h2>
    <p class="muted">Identyfikator workspace otrzymasz od DoSieci po założeniu subskrypcji w panelu klienta.</p>
    <form method="post" action="{{ route('settings.billing.link') }}" class="filters">
        @csrf
        <label>Workspace ID
            <input type="text" name="license_hub_workspace_id" value="{{ $company->license_hub_workspace_id }}" placeholder="np. 1001">
        </label>
        <button class="btn">Zapisz i odśwież</button>
    </form>
</div>
@endsection
