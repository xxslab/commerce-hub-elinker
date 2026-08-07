@extends('layouts.app')
@section('content')
<h1>Kolejka i synchronizacja</h1>
<div class="grid">
    <div class="card"><div class="muted">Jobs w kolejce</div><div class="big">{{ $queued }}</div></div>
    <div class="card"><div class="muted">Aktualnie zarezerwowane</div><div class="big">{{ $reserved }}</div></div>
    <div class="card"><div class="muted">Failed jobs</div><div class="big">{{ $failed }}</div></div>
    <div class="card"><div class="muted">Kanały syncing</div><div class="big">{{ $channels->where('sync_status','syncing')->count() }}</div></div>
</div>
<div class="card">
<h2>Kanały</h2>
<table><thead><tr><th>Status</th><th>Nazwa</th><th>Ostatni sync</th><th>Ostatni błąd</th></tr></thead><tbody>
@foreach($channels as $channel)
@php($status = $channel->sync_status ?: 'idle')
<tr><td><span class="badge {{ $status }}"><span class="dot {{ $status }}"></span>{{ $status }}</span></td><td>{{ $channel->name }}</td><td>{{ optional($channel->last_sync_at)->format('Y-m-d H:i:s') ?: '-' }}</td><td class="err">{{ $channel->last_error }}</td></tr>
@endforeach
</tbody></table>
</div>
<div class="card">
<h2>Ostatnie jobs</h2>
<table><thead><tr><th>ID</th><th>Queue</th><th>Attempts</th><th>Reserved</th><th>Created</th></tr></thead><tbody>
@forelse($jobs as $job)
<tr><td>{{ $job->id }}</td><td>{{ $job->queue }}</td><td>{{ $job->attempts }}</td><td>{{ $job->reserved_at ? 'tak' : 'nie' }}</td><td>{{ date('Y-m-d H:i:s',$job->created_at) }}</td></tr>
@empty<tr><td colspan="5">Brak jobs w kolejce.</td></tr>@endforelse
</tbody></table>
</div>
<div class="card">
<h2>Failed jobs</h2>
@if($failed > 0)<form method="post" action="{{ route('queue.failed.clear') }}" onsubmit="return confirm('Wyczyścić wszystkie obsłużone failed jobs?')">@csrf<button class="btn" style="background:#b91c1c">Wyczyść failed jobs</button></form>@endif
<table><thead><tr><th>ID</th><th>Queue</th><th>Failed at</th><th>Exception</th></tr></thead><tbody>
@forelse($failedJobs as $job)
<tr><td>{{ $job->id }}</td><td>{{ $job->queue }}</td><td>{{ $job->failed_at }}</td><td class="err">Szczegóły zapisane w logach serwera.</td></tr>
@empty<tr><td colspan="4">Brak failed jobs.</td></tr>@endforelse
</tbody></table>
</div>
@endsection
