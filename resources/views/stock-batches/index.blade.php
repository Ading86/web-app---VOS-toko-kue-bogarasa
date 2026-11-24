@extends('layouts.app')
@section('title', 'Stok Batch Kue')
@section('heading', 'Stok Batch Kue Basah & Kering')
@section('breadcrumb', 'Operasional / Stok Batch')
@section('actions')
    @can('inventory.manage')
        <a href="{{ route('stock-batches.create') }}" class="btn-primary">Catat stok masuk batch</a>
    @endcan
@endsection
@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-5">
    @if ($branches->isNotEmpty())
        <select name="branch_id" class="input">
            <option value="">Semua cabang</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>
            @endforeach
        </select>
    @endif
    <select name="product_id" class="input">
        <option value="">Semua produk</option>
        @foreach ($products as $p)
            <option value="{{ $p->id }}" @selected(request('product_id') === $p->id)>{{ $p->nama_produk }}</option>
        @endforeach
    </select>
    <select name="tipe" class="input">
        <option value="">Semua tipe</option>
        @foreach ($tipeOptions as $k => $v)
            <option value="{{ $k }}" @selected(request('tipe') === $k)>{{ $v }}</option>
        @endforeach
    </select>
    <select name="status" class="input">
        <option value="">Semua status</option>
        <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
        <option value="kedaluwarsa" @selected(request('status') === 'kedaluwarsa')>Kedaluwarsa</option>
    </select>
    <button class="btn-secondary" type="submit">Terapkan</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead>
            <tr>
                <th class="th">Produk</th>
                <th class="th">Tipe</th>
                <th class="th">Cabang</th>
                <th class="th text-right">Tgl Produksi</th>
                <th class="th text-right">Kedaluwarsa</th>
                <th class="th text-right">Masuk</th>
                <th class="th text-right">Terjual</th>
                <th class="th text-right">Kedaluwarsa</th>
                <th class="th text-right font-semibold">Sisa Layak Jual</th>
                <th class="th">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($batches as $b)
            @php
                $today = \Illuminate\Support\Carbon::now('Asia/Jakarta')->startOfDay();
                $isExpired = $b->tanggal_kedaluwarsa->lt($today) || $b->status === 'kedaluwarsa';
                $sisaLayak = $isExpired ? 0 : $b->jumlah_sisa;
            @endphp
            <tr class="{{ $isExpired ? 'bg-stone-50/60' : '' }}">
                <td class="td font-medium">{{ $b->product?->nama_produk }}</td>
                <td class="td">
                    @if ($b->product?->tipe_kue === 'basah')
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 font-medium">Basah (1 hari)</span>
                    @elseif ($b->product?->tipe_kue === 'kering')
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 font-medium">Kering (~{{ $b->product?->masa_layak_jual ?? 5 }} hari)</span>
                    @endif
                </td>
                <td class="td">{{ $b->branch?->nama_cabang }}</td>
                <td class="td text-right font-mono text-xs">{{ $b->tanggal_produksi->format('d/m/Y') }}</td>
                <td class="td text-right font-mono text-xs {{ $isExpired ? 'text-stone-400' : 'font-semibold text-stone-700' }}">
                    {{ $b->tanggal_kedaluwarsa->format('d/m/Y') }}
                </td>
                <td class="td text-right">{{ $b->jumlah_masuk }}</td>
                <td class="td text-right">{{ $b->jumlah_terjual }}</td>
                <td class="td text-right text-stone-500">{{ $b->jumlah_kadaluwarsa > 0 ? $b->jumlah_kadaluwarsa : ($isExpired ? max(0, $b->jumlah_masuk - $b->jumlah_terjual) : 0) }}</td>
                <td class="td text-right font-semibold {{ $sisaLayak > 0 ? 'text-emerald-700' : 'text-stone-400' }}">
                    {{ $sisaLayak }}
                </td>
                <td class="td">
                    @if ($isExpired)
                        <span class="badge-bad">Kedaluwarsa</span>
                    @elseif ($b->jumlah_sisa <= 0)
                        <span class="badge-mute">Habis Terjual</span>
                    @else
                        <span class="badge-ok">Aktif</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="td py-10 text-center text-stone-400">Belum ada data batch stok.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $batches->links() }}</div>
@endsection
