@extends('layouts.app')
@section('title', 'Ubah Kategori')
@section('heading', 'Ubah Kategori')
@section('breadcrumb', 'Master Data / Kategori / Ubah')
@section('content')
<form method="POST" action="{{ route('categories.update', $category) }}" class="card max-w-lg space-y-4 p-6">
    @csrf @method('PUT')
    <div><label class="label">Nama kategori</label><input name="nama_kategori" value="{{ old('nama_kategori', $category->nama_kategori) }}" class="input" required></div>
    <div><label class="label">Status</label><select name="status" class="input">
        @foreach (['aktif', 'nonaktif'] as $s)<option value="{{ $s }}" @selected(old('status', $category->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="flex gap-2"><button class="btn-primary" type="submit">Simpan</button><a href="{{ route('categories.index') }}" class="btn-secondary">Batal</a></div>
</form>
@endsection
