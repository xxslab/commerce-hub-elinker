<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logowanie — Commerce Hub</title></head>
<body style="font-family:Arial;max-width:420px;margin:60px auto;padding:20px">
<h1>Commerce Hub</h1><h2>Logowanie</h2>
@if($errors->any())<div style="color:#b91c1c">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login') }}">@csrf
<p><label>E-mail<br><input type="email" name="email" value="{{ old('email') }}" required autofocus></label></p>
<p><label>Hasło<br><input type="password" name="password" required></label></p>
<p><label><input type="checkbox" name="remember"> Zapamiętaj mnie</label></p>
<button type="submit">Zaloguj</button>
</form>
<p><a href="{{ route('register') }}">Utwórz konto</a></p>
</body></html>
