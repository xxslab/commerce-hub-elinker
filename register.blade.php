<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rejestracja — Commerce Hub</title></head>
<body style="font-family:Arial;max-width:420px;margin:60px auto;padding:20px">
<h1>Commerce Hub</h1><h2>Utwórz konto</h2>
@if($errors->any())<div style="color:#b91c1c">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('register') }}">@csrf
<p><label>Nazwa firmy / użytkownika<br><input name="name" value="{{ old('name') }}" required></label></p>
<p><label>E-mail<br><input type="email" name="email" value="{{ old('email') }}" required></label></p>
<p><label>Hasło (min. 12 znaków)<br><input type="password" name="password" required></label></p>
<p><label>Powtórz hasło<br><input type="password" name="password_confirmation" required></label></p>
<button type="submit">Utwórz konto</button>
</form><p><a href="{{ route('login') }}">Mam już konto</a></p>
</body></html>
