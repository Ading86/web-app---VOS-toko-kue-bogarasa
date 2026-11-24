@extends('layouts.app')
@php($editing = $user->exists)
@section('title', $editing ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('heading', $editing ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('breadcrumb', 'Pengaturan / Pengguna / ' . ($editing ? 'Ubah' : 'Tambah'))
@section('content')
<form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
      class="card max-w-2xl space-y-4 p-6" x-data="{ role: @js(old('role', $currentRole ?? 'Kasir')) }">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div><label class="label">Nama</label><input name="name" value="{{ old('name', $user->name) }}" class="input" required></div>
    <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="input" required></div>
    <div>
        <label class="label">Password {{ $editing ? '(kosongkan jika tidak diganti)' : '' }}</label>
        <input type="password" name="password" class="input" autocomplete="new-password" {{ $editing ? '' : 'required' }}>
        <p class="mt-1 text-xs text-stone-500">Min. 8 karakter, huruf dan angka. Pengguna dapat mengubah password sewaktu-waktu di menu Pengaturan Akun.</p>
    </div>
    <div>
        <label class="label">Role</label>
        <select name="role" x-model="role" class="input" required>
            @foreach ($roles as $r)<option value="{{ $r->name }}">{{ $r->name }}</option>@endforeach
        </select>
    </div>
    <div x-show="role !== 'Owner'" x-cloak>
        <label class="label">Cabang yang ditugaskan</label>
        <select name="branch_id" class="input">
            <option value="">-- pilih cabang --</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $user->branch_id) === $b->id)>{{ $b->nama_cabang }} ({{ $b->kode_cabang }})</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-stone-500">Akun selain Owner hanya dapat mengakses cabang ini.</p>
    </div>
    @if ($editing)
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded border-stone-300"
                   @checked(old('is_active', $user->is_active)) @disabled($user->is(auth()->user()))>
            Akun aktif
        </label>
        @if ($user->is(auth()->user()))<input type="hidden" name="is_active" value="1">@endif
    @endif
    <div class="flex gap-2 pt-2">
        <button type="submit" class="btn-primary">Simpan</button>
        <a href="{{ route('users.index') }}" class="btn-secondary">Batal</a>
    </div>
</form>
@endsection
