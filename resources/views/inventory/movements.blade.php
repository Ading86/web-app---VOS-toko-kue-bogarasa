@extends('layouts.app')
@section('title', 'Riwayat Pergerakan Stok')
@section('heading', 'Riwayat Pergerakan Stok')
@section('breadcrumb', 'Operasional / Persediaan / Riwayat Stok')

@section('actions')
    <a href="{{ route('inventory.index') }}" class="btn-secondary">Kembali ke Persediaan</a>
@endsection

@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
    @if ($branches->isNotEmpty())
        <select name="branch_id" class="input">
            <option value="">Semua Cabang</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>
            @endforeach
        </select>
    @endif

    <select name="product_id" class="input">
        <option value="">Semua Produk</option>
        @foreach ($products as $p)
            <option value="{{ $p->id }}" @selected(request('product_id') === $p->id)>{{ $p->nama_produk }}</option>
        @endforeach
    </select>

    <select name="jenis" class="input">
        <option value="">Semua Jenis</option>
        @foreach ($jenisLabels as $k => $v)
            <option value="{{ $k }}" @selected(request('jenis') === $k)>{{ $v }}</option>
        @endforeach
    </select>

    <select name="lokasi" class="input">
        <option value="">Semua Lokasi</option>
        <option value="jumlah_stok" @selected(request('lokasi') === 'jumlah_stok')>Stok Jual</option>
        <option value="stok_gudang" @selected(request('lokasi') === 'stok_gudang')>Gudang Pusat</option>
        <option value="stok_perjalanan" @selected(request('lokasi') === 'stok_perjalanan')>Dalam Perjalanan</option>
    </select>

    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input" title="Dari Tanggal">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input" title="Sampai Tanggal">

    <div class="flex gap-2 sm:col-span-2 lg:col-span-6 justify-end">
        @if (request()->hasAny(['branch_id', 'product_id', 'jenis', 'lokasi', 'date_from', 'date_to']))
            <a href="{{ route('inventory.movements') }}" class="btn-secondary">Reset</a>
        @endif
        <button class="btn-secondary" type="submit">Terapkan Filter</button>
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead>
            <tr>
                <th class="th">Waktu</th>
                @if (auth()->user()->isOwner())
                    <th class="th">Cabang</th>
                @endif
                <th class="th">Produk</th>
                <th class="th">Lokasi</th>
                <th class="th">Jenis Pergerakan</th>
                <th class="th text-right">Perubahan</th>
                <th class="th text-center">Stok</th>
                <th class="th">Petugas</th>
                <th class="th">Keterangan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($movements as $m)
            <tr>
                <td class="td text-xs text-stone-500 whitespace-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                @if (auth()->user()->isOwner())
                    <td class="td font-medium">{{ $m->branch?->nama_cabang }}</td>
                @endif
                <td class="td font-medium text-stone-900">
                    {{ $m->product?->nama_produk ?? 'Produk Dihapus' }}
                </td>
                <td class="td text-xs">
                    @if ($m->lokasi_stok === 'stok_gudang')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-800">Gudang Pusat</span>
                    @elseif ($m->lokasi_stok === 'stok_perjalanan')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-purple-50 text-purple-800">Perjalanan</span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-stone-100 text-stone-700">Stok Jual</span>
                    @endif
                </td>
                <td class="td">
                    @php
                        $jenis = $m->jenis_pergerakan;
                        $badgeClass = match ($jenis) {
                            \App\Models\StockMovement::MASUK,
                            \App\Models\StockMovement::BATCH_MASUK,
                            \App\Models\StockMovement::DIST_TERIMA => 'badge-ok',

                            \App\Models\StockMovement::KELUAR,
                            \App\Models\StockMovement::PENJUALAN,
                            \App\Models\StockMovement::PEMBAGIAN,
                            \App\Models\StockMovement::KEDALUWARSA => 'badge-bad',

                            default => 'badge-warn',
                        };
                    @endphp
                    <span class="{{ $badgeClass }} text-[11px]">
                        {{ $jenisLabels[$jenis] ?? $jenis }}
                    </span>
                </td>
                <td class="td text-right font-mono font-semibold {{ $m->jumlah > 0 ? 'text-emerald-600' : ($m->jumlah < 0 ? 'text-red-600' : 'text-stone-600') }}">
                    {{ $m->jumlah > 0 ? '+' . number_format($m->jumlah) : number_format($m->jumlah) }}
                </td>
                <td class="td text-center font-mono text-xs text-stone-500 whitespace-nowrap">
                    {{ number_format($m->stok_sebelum) }} &rarr; {{ number_format($m->stok_sesudah) }}
                </td>
                <td class="td text-xs text-stone-600">{{ $m->user?->name ?? '-' }}</td>
                <td class="td text-xs text-stone-500 max-w-xs truncate" title="{{ $m->keterangan }}">
                    {{ $m->keterangan ?: '-' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ auth()->user()->isOwner() ? 9 : 8 }}" class="td py-10 text-center text-stone-400">
                    Tidak ada riwayat pergerakan stok.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $movements->links() }}
</div>
@endsection
