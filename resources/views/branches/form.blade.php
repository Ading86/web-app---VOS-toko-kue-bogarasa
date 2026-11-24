@extends('layouts.app')
@php($editing = $branch->exists)
@section('title', $editing ? 'Ubah Cabang' : 'Tambah Cabang')
@section('heading', $editing ? 'Ubah Cabang' : 'Tambah Cabang')
@section('breadcrumb', 'Master Data / Cabang / ' . ($editing ? 'Ubah' : 'Tambah'))
@section('content')
<form method="POST" action="{{ $editing ? route('branches.update', $branch) : route('branches.store') }}" class="card max-w-2xl space-y-4 p-6">
    @csrf @if ($editing) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Kode cabang</label><input name="kode_cabang" value="{{ old('kode_cabang', $branch->kode_cabang) }}" class="input" maxlength="10" @required($editing) placeholder="{{ $editing ? '' : 'kosongkan = otomatis (BRNxx)' }}">
            <p class="mt-1 text-xs text-stone-500">Maks 10 karakter. Dipakai pada kode struk (mis. BRN02-20261031-0001).</p></div>
        <div><label class="label">Nama cabang</label><input name="nama_cabang" value="{{ old('nama_cabang', $branch->nama_cabang) }}" class="input" maxlength="60" required></div>
    </div>
    <div><label class="label">Alamat</label><input name="alamat" value="{{ old('alamat', $branch->alamat) }}" class="input" maxlength="150"></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Nomor telepon</label><input name="nomor_telepon" value="{{ old('nomor_telepon', $branch->nomor_telepon) }}" class="input" inputmode="tel" maxlength="20">
            <p class="mt-1 text-xs text-stone-500">8 sampai 13 digit, diawali 0 (+62 otomatis diubah menjadi 0).</p></div>
        <div><label class="label">Status</label><select name="status" class="input">
            @foreach (['aktif', 'nonaktif'] as $s)<option value="{{ $s }}" @selected(old('status', $branch->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    </div>
    @if ($editing && $branch->is_pusat)<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Ini <strong>Cabang Pusat</strong>: sumber distribusi stok ke semua cabang. Cabang Pusat tidak dapat dinonaktifkan.</p>@endif
    <div class="flex gap-2 pt-2"><button type="submit" class="btn-primary">Simpan</button><a href="{{ route('branches.index') }}" class="btn-secondary">Batal</a></div>
</form>
@endsection
