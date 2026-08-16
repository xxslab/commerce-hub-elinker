@extends('layouts.app')

@section('content')
    <h1>Edytuj nazwę kanału</h1>

    <div class="card">
        <form method="POST" action="{{ route('channels.update', $salesChannel) }}">
            @csrf
            @method('PATCH')

            <label>Nazwa kanału</label>
            <input name="name" value="{{ old('name', $salesChannel->name) }}" required maxlength="255">

            <p class="muted">
                Typ: {{ $salesChannel->type }}<br>
                Domena: {{ $salesChannel->display_domain }}
            </p>

            <button class="btn" type="submit">Zapisz nazwę</button>
            <a class="btn btn2" href="{{ route('channels.index') }}">Anuluj</a>
        </form>
    </div>
@endsection
