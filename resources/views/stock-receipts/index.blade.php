@extends('layouts.app')
@section('title', 'Penerimaan Stok')
@section('heading', 'Penerimaan Stok Gudang Pusat')
@section('breadcrumb', 'Operasional / Penerimaan Stok')

@section('actions')
    @can('inventory.manage')
        <a href="{{ route('stock-receipts.create') }}" class="btn-primary flex items-center gap-2">
            <span>Penerimaan Stok Baru</span>
        </a>
    @endcan
@endsection

@section('content')
<div class="mb-4 rounded-lg bg-brand-50 p-4 text-sm text-stone-700">
    Halaman pencatatan seluruh stok kue yang masuk ke <strong>Gudang Pusat</strong> setiap harinya. 
    Stok kue basah berlaku maksimal 1 hari (hari ini), sedangkan kue kering memiliki masa layak jual hingga beberapa hari.
    Stok yang telah diterima dapat didistribusikan ke cabang melalui menu <strong>Distribusi Stok</strong>.
</div>

<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
    <input name="search" value="{{ request('search') }}" placeholder="Cari nomor penerimaan atau catatan..." class="input">
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input" title="Tanggal Dari">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input" title="Tanggal Sampai">
    <div class="flex gap-2">
        <button class="btn-secondary w-full" type="submit">Terapkan</button>
        @if (request()->hasAny(['search', 'date_from', 'date_to']))
            <a href="{{ route('stock-receipts.index') }}" class="btn-secondary">Reset</a>
        @endif
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead>
            <tr>
                <th class="th">Nomor Penerimaan</th>
                <th class="th">Tanggal</th>
                <th class="th">Petugas</th>
                <th class="th text-right">Jenis Produk</th>
                <th class="th text-right">Total Kuantitas</th>
                <th class="th">Catatan</th>
                <th class="th text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($receipts as $r)
            <tr>
                <td class="td font-mono text-xs font-semibold text-brand-700">
                    <a href="{{ route('stock-receipts.show', $r) }}" class="hover:underline">
                        {{ $r->nomor_penerimaan }}
                    </a>
                </td>
                <td class="td">{{ $r->tanggal_penerimaan->format('d/m/Y') }}</td>
                <td class="td font-medium">{{ $r->user?->name ?? '-' }}</td>
                <td class="td text-right font-semibold">{{ number_format($r->total_item) }}</td>
                <td class="td text-right font-semibold text-stone-900">{{ number_format($r->total_kuantitas) }} pcs</td>
                <td class="td max-w-xs truncate text-stone-500">{{ $r->catatan ?: '-' }}</td>
                <td class="td text-right">
                    <a href="{{ route('stock-receipts.show', $r) }}" class="btn-secondary btn-sm">
                        Detail
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="td py-10 text-center text-stone-400">
                    Belum ada data penerimaan stok.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $receipts->links() }}
</div>
@endsection
