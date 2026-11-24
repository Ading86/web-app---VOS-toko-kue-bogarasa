@extends('layouts.app')
@section('title', 'Persediaan')
@section('heading', auth()->user()->isOwner() ? 'Persediaan per Cabang' : 'Stok Cabang')
@section('breadcrumb', 'Operasional / Persediaan')

@section('actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('inventory.movements') }}" class="btn-secondary">Riwayat stok</a>
        @can('inventory.manage')
            @if (!empty($hasBasahStock))
                <form method="POST" action="{{ route('inventory.clear-basah') }}" class="inline" onsubmit="return confirm('Kosongkan seluruh sisa stok kue basah{{ $currentBranchId ? ' di cabang ini' : ' di semua cabang' }}?')">
                    @csrf
                    @if ($currentBranchId)
                        <input type="hidden" name="branch_id" value="{{ $currentBranchId }}">
                    @endif
                    <button type="submit" class="btn-danger flex items-center gap-1.5" title="Kosongkan seluruh sisa stok kue basah">
                        <x-icon name="trash" class="h-4 w-4" />
                        <span>Clear Sisa Kue Basah</span>
                    </button>
                </form>
            @endif
            <a href="{{ route('inventory.create') }}" class="btn-primary">Ubah stok</a>
        @endcan
    </div>
@endsection

@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
    <input name="search" value="{{ request('search') }}" placeholder="Cari produk" class="input">
    @if ($branches->isNotEmpty())
        <select name="branch_id" class="input"><option value="">Semua cabang</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select>
    @endif
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="low" value="1" @checked(request('low')) class="rounded border-stone-300"> Hanya stok menipis</label>
    <button class="btn-secondary" type="submit">Terapkan</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Produk</th><th class="th">Kategori</th>@if ($showBranch)<th class="th">Cabang</th>@endif
            <th class="th text-right">Stok jual</th><th class="th text-right">Gudang pusat</th><th class="th text-right">Dalam perjalanan</th><th class="th text-right">Minimum</th><th class="th">Status</th>@can('inventory.manage')<th class="th text-right">Aksi</th>@endcan</tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($stocks as $s)
            <tr>
                <td class="td font-medium">
                    {{ $s->product?->nama_produk }}
                    @if ($s->product?->isBasah())
                        <span class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-800">Basah</span>
                    @elseif ($s->product?->isKering())
                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">Kering</span>
                    @endif
                </td>
                <td class="td">{{ $s->product?->category?->nama_kategori }}</td>
                @if ($showBranch)<td class="td">{{ $s->branch?->nama_cabang }}</td>@endif
                <td class="td text-right font-semibold">{{ $s->jumlah_stok }}</td>
                <td class="td text-right">@if ($s->branch?->is_pusat){{ $s->stok_gudang }}@else<span class="text-stone-300">-</span>@endif</td>
                <td class="td text-right">{{ $s->stok_perjalanan > 0 ? $s->stok_perjalanan : '-' }}</td>
                <td class="td text-right">{{ $s->stok_minimum }}</td>
                <td class="td">
                    @if ($s->jumlah_stok === 0)<span class="badge-bad">Habis</span>
                    @elseif ($s->isLow())<span class="badge-warn">Menipis</span>
                    @else<span class="badge-ok">Aman</span>@endif
                </td>
                @can('inventory.manage')
                    <td class="td text-right">
                        <div class="inline-flex items-center gap-1.5">
                            @if ($s->product?->isBasah() && ($s->jumlah_stok > 0 || ($s->branch?->is_pusat && $s->stok_gudang > 0)))
                                <form method="POST" action="{{ route('inventory.clear-basah') }}" class="inline" onsubmit="return confirm('Kosongkan sisa stok kue basah {{ addslashes($s->product?->nama_produk) }} di {{ addslashes($s->branch?->nama_cabang) }}?')">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $s->branch_id }}">
                                    <input type="hidden" name="product_id" value="{{ $s->product_id }}">
                                    <button type="submit" class="btn-danger btn-sm" title="Kosongkan sisa stok kue basah ini">Clear</button>
                                </form>
                            @endif
                            <a class="btn-secondary btn-sm" href="{{ route('inventory.create', ['branch_id' => $s->branch_id, 'product_id' => $s->product_id]) }}">Ubah</a>
                        </div>
                    </td>
                @endcan
            </tr>
        @empty
            <tr><td colspan="9" class="td py-10 text-center text-stone-400">Tidak ada data stok.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $stocks->links() }}</div>
@endsection
