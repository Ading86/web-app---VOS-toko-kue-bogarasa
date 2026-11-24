@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<form method="POST" action="{{ route('login.store') }}" class="space-y-4">
    @csrf
    <div>
        <label class="label" for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="username">
    </div>
    <div>
        <label class="label" for="password">Password</label>
        <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
    </div>
    <label class="flex items-center gap-2 text-sm text-stone-600">
        <input type="checkbox" name="remember" value="1" class="rounded border-stone-300"> Ingat saya
    </label>
    <button type="submit" class="btn-primary w-full">Masuk</button>
</form>
@endsection
