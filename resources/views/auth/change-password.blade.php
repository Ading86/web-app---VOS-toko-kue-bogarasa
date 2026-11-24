@php($forced = auth()->user()->must_change_password)
@extends($forced ? 'layouts.guest' : 'layouts.app')
@section('title', 'Ganti Password')
@section('heading', 'Ganti Password')
@section('content')
<div class="{{ $forced ? '' : 'card max-w-lg p-6' }}">
    @if ($forced)
        <h2 class="mb-1 text-lg font-semibold text-brand-900">Ganti password dulu</h2>
        <p class="mb-4 text-sm text-stone-500">Demi keamanan, password sementara wajib diganti sebelum memakai aplikasi.</p>
    @endif
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf @method('PUT')
        <div><label class="label">Password saat ini</label><input type="password" name="current_password" class="input" required autocomplete="current-password"></div>
        <div><label class="label">Password baru</label><input type="password" name="password" class="input" required autocomplete="new-password">
            <p class="mt-1 text-xs text-stone-500">Minimal 8 karakter, mengandung huruf dan angka.</p></div>
        <div><label class="label">Ulangi password baru</label><input type="password" name="password_confirmation" class="input" required autocomplete="new-password"></div>
        <button type="submit" class="btn-primary">Simpan password</button>
        @if ($forced)
            <button type="submit" form="logout-form" class="btn-secondary" data-nolock="1">Keluar</button>
        @endif
    </form>
    @if ($forced)
        <form id="logout-form" method="POST" action="{{ route('logout') }}" data-nolock="1" class="hidden">@csrf</form>
    @endif
</div>
@endsection
