<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commerce Hub</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f6f7fb;color:#111827}.wrap{max-width:1200px;margin:0 auto;padding:24px}.nav{background:#111827;color:#fff;padding:14px 24px}.nav a{color:#fff;margin-right:18px;text-decoration:none}.card{background:#fff;border-radius:12px;padding:18px;margin:16px 0;box-shadow:0 1px 6px rgba(0,0,0,.08)}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}.badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700}.idle{background:#dcfce7;color:#166534}.syncing{background:#fef3c7;color:#92400e}.error{background:#fee2e2;color:#991b1b}.dot{width:10px;height:10px;border-radius:50%;display:inline-block}.dot.idle{background:#22c55e}.dot.syncing{background:#f59e0b}.dot.error{background:#ef4444}.btn{border:0;background:#2563eb;color:white;padding:8px 12px;border-radius:8px;cursor:pointer}.muted{color:#6b7280}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.big{font-size:28px;font-weight:700}.err{max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#991b1b}
.source-badge{display:inline-flex;align-items:center;gap:6px;border-radius:6px;padding:3px 9px;font-size:12px;font-weight:700;color:#fff;white-space:nowrap}
.source-woocommerce{background:#7f54b3}.source-allegro{background:#ff5a00}.source-ebay{background:#0064d2}.source-other{background:#6b7280}
.filters{display:flex;flex-wrap:wrap;gap:8px;align-items:end}
.filters label{display:flex;flex-direction:column;font-size:11px;color:#6b7280;gap:3px}
.filters input,.filters select{padding:7px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
.pay-paid{color:#166534;font-weight:600}.pay-unknown{color:#6b7280}.pay-pending{color:#92400e}
.table-scroll{overflow-x:auto}
    </style>
</head>
<body>
<div class="nav">
    <a href="{{ route('dashboard') }}">Dashboard</a>
    <a href="{{ route('orders.index') }}">Zamówienia</a>
    <a href="{{ route('queue.index') }}">Kolejka</a>
    <a href="{{ route('channels.index') }}">Kanały</a>
    <a href="{{ route('settings.billing') }}">Ustawienia</a>
    @auth
        <span style="float:right">{{ auth()->user()->maskedEmail() }}
        <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf<button type="submit">Wyloguj</button></form></span>
    @endauth
</div>
<div class="wrap">
    @if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
    @if(session('error'))<div class="card" style="color:#991b1b">{{ session('error') }}</div>@endif
    @if(session('status'))<div class="card">{{ session('status') }}</div>@endif
    @yield('content')
</div>
</body>
</html>
