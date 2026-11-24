@extends('layouts.app')
@section('title', 'Kategori')
@section('heading', 'Kategori Produk')
@section('breadcrumb', 'Master Data / Kategori')
@section('content')
@can('categories.manage')
<form method="POST" action="{{ route('categories.store') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
    @csrf
    <div class="min-w-14rem flex-1"><label class="label">Kategori baru</label><input name="nama_kategori" value="{{ old('nama_kategori') }}" class="input" required></div>
    <div><label class="label">Status</label><select name="status" class="input"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></div>
    <button class="btn-primary" type="submit">Tambah</button>
</form>
@endcan
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">ID</th><th class="th">Nama</th><th class="th">Produk</th><th class="th">Status</th><th class="th text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($categories as $c)
            <tr>
                <td class="td font-mono text-xs">{{ $c->id }}</td><td class="td font-medium">{{ $c->nama_kategori }}</td><td class="td">{{ $c->products_count }}</td>
                <td class="td"><span class="{{ $c->status === 'aktif' ? 'badge-ok' : 'badge-bad' }}">{{ ucfirst($c->status) }}</span></td>
                <td class="td text-right">@can('categories.manage')
                    <a href="{{ route('categories.edit', $c) }}" class="btn-secondary btn-sm">Ubah</a>
                    @if ($c->products_count === 0)
                        <form method="POST" action="{{ route('categories.destroy', $c) }}" class="inline" data-confirm="Hapus kategori {{ $c->nama_kategori }}?">@csrf @method('DELETE')<button class="btn-danger btn-sm" type="submit">Hapus</button></form>
                    @endif
                @endcan</td>
            </tr>
        @empty
            <tr><td colspan="5" class="td py-10 text-center text-stone-400">Belum ada kategori.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $categories->links() }}</div>
@endsection
