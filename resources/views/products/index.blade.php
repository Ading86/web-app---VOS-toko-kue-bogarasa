@extends('layouts.app')
@section('title', 'Produk')
@section('heading', 'Produk')
@section('breadcrumb', 'Master Data / Produk')
@section('actions')@can('products.create')<a href="{{ route('products.create') }}" class="btn-primary">Tambah Produk</a>@endcan @endsection
@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
    <input name="search" value="{{ request('search') }}" placeholder="Cari nama / kode produk" class="input">
    <select name="category_id" class="input"><option value="">Semua kategori</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') === $c->id)>{{ $c->nama_kategori }}</option>@endforeach</select>
    <select name="status" class="input"><option value="">Semua status</option>
        @foreach (['aktif', 'nonaktif'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
    <button class="btn-secondary" type="submit">Terapkan</button>
</form>
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Produk</th><th class="th">Kode</th><th class="th">Kategori</th><th class="th text-right">Harga</th><th class="th">Status</th><th class="th text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($products as $p)
            <tr>
                <td class="td"><div class="flex items-center gap-3">
                    @if ($p->gambar)<img src="{{ asset('storage/' . $p->gambar) }}" alt="" class="h-10 w-10 rounded-lg object-cover">
                    @else<div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-300"><x-icon name="image" class="h-5 w-5" /></div>@endif
                    <span class="font-medium">{{ $p->nama_produk }}</span></div></td>
                <td class="td font-mono text-xs">{{ $p->kode_produk }}</td>
                <td class="td">{{ $p->category?->nama_kategori }}</td>
                <td class="td text-right">Rp {{ number_format($p->harga, 0, ',', '.') }}</td>
                <td class="td"><span class="{{ $p->status === 'aktif' ? 'badge-ok' : 'badge-bad' }}">{{ ucfirst($p->status) }}</span></td>
                <td class="td text-right">
                    @can('products.edit')
                        <a href="{{ route('products.edit', $p) }}" class="btn-secondary btn-sm">Ubah</a>
                        <form method="POST" action="{{ route('products.toggle', $p) }}" class="inline">@csrf<button class="btn-secondary btn-sm" type="submit">{{ $p->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                    @endcan
                    @can('products.delete')
                        <form method="POST" action="{{ route('products.destroy', $p) }}" class="inline" data-confirm="Hapus produk {{ $p->nama_produk }}? Riwayat transaksi tetap aman.">@csrf @method('DELETE')<button class="btn-danger btn-sm" type="submit">Hapus</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="td py-12 text-center text-stone-400"><x-icon name="inbox" class="mx-auto mb-2 h-8 w-8 text-stone-300" />Belum ada produk.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
