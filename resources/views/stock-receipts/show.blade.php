@extends('layouts.app')
@section('title', 'Detail Penerimaan ' . $stockReceipt->nomor_penerimaan)
@section('heading', 'Rincian Penerimaan Stok')
@section('breadcrumb', 'Operasional / Penerimaan Stok / Detail')

@section('actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('stock-receipts.index') }}" class="btn-secondary">Kembali</a>
        @can('distributions.create')
            <a href="{{ route('distributions.create') }}" class="btn-primary flex items-center gap-1.5">
                <x-icon name="truck" class="h-4 w-4" />
                <span>Distribusikan ke Cabang</span>
            </a>
        @endcan
    </div>
@endsection

@section('content')
<div class="space-y-6">
    {{-- Ringkasan Penerimaan Header --}}
    <div class="card p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-stone-200 pb-4">
            <div>
                <span class="text-xs uppercase tracking-wider text-stone-500 font-semibold">Nomor Penerimaan</span>
                <h2 class="text-2xl font-bold font-mono text-brand-700">{{ $stockReceipt->nomor_penerimaan }}</h2>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <span class="text-xs text-stone-500 block">Total Jenis</span>
                    <span class="text-lg font-bold text-stone-800">{{ number_format($stockReceipt->total_item) }} jenis</span>
                </div>
                <div class="h-8 w-px bg-stone-200"></div>
                <div class="text-right">
                    <span class="text-xs text-stone-500 block">Total Kuantitas</span>
                    <span class="text-lg font-bold text-brand-700">{{ number_format($stockReceipt->total_kuantitas) }} pcs</span>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <span class="text-stone-500 block text-xs">Tanggal Penerimaan</span>
                <span class="font-semibold text-stone-800">{{ $stockReceipt->tanggal_penerimaan->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="text-stone-500 block text-xs">Lokasi Penerimaan</span>
                <span class="font-semibold text-stone-800">{{ $stockReceipt->branch?->nama_cabang ?? 'Gudang Pusat' }}</span>
            </div>
            <div>
                <span class="text-stone-500 block text-xs">Petugas Penerima</span>
                <span class="font-semibold text-stone-800">{{ $stockReceipt->user?->name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-stone-500 block text-xs">Waktu Pencatatan</span>
                <span class="font-semibold text-stone-800">{{ $stockReceipt->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        @if ($stockReceipt->catatan)
            <div class="mt-4 pt-3 border-t border-stone-100 text-sm">
                <span class="text-stone-500 text-xs block">Catatan:</span>
                <p class="text-stone-700 italic mt-0.5">{{ $stockReceipt->catatan }}</p>
            </div>
        @endif
    </div>

    {{-- Tabel Detail Produk --}}
    <div class="card overflow-x-auto p-5">
        <h3 class="text-base font-semibold text-stone-800 mb-4 border-b border-stone-200 pb-2">Rincian Produk Masuk</h3>
        <table class="min-w-full divide-y divide-stone-200">
            <thead>
                <tr>
                    <th class="th w-12 text-center">No</th>
                    <th class="th">Produk</th>
                    <th class="th">Kategori / Tipe</th>
                    <th class="th text-center">Masa Layak Jual</th>
                    <th class="th text-center">Tanggal Kedaluwarsa</th>
                    <th class="th text-right">Jumlah Diterima</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($stockReceipt->details as $index => $detail)
                <tr>
                    <td class="td text-center text-stone-400 font-mono text-xs">{{ $index + 1 }}</td>
                    <td class="td">
                        <div class="font-medium text-stone-900">{{ $detail->product?->nama_produk ?? 'Produk Dihapus' }}</div>
                        <div class="text-xs font-mono text-stone-400">{{ $detail->product?->kode_produk ?? '-' }}</div>
                    </td>
                    <td class="td">
                        <div class="text-xs font-semibold text-stone-700">{{ $detail->product?->category?->nama_kategori ?? '-' }}</div>
                        <div class="mt-0.5">
                            @if ($detail->product?->tipe_kue === 'basah')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800">
                                    Kue Basah
                                </span>
                            @elseif ($detail->product?->tipe_kue === 'kering')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                    Kue Kering
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-stone-100 text-stone-700">
                                    Lainnya
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="td text-center text-xs">
                        <span class="font-semibold text-stone-800">{{ $detail->masa_layak_jual }} Hari</span>
                        @if ($detail->product?->tipe_kue === 'basah')
                            <div class="text-[10px] text-blue-600 font-medium">(Maksimal 1 Hari)</div>
                        @endif
                    </td>
                    <td class="td text-center text-xs">
                        @if ($detail->tanggal_kedaluwarsa)
                            <span class="font-semibold text-brand-700">
                                {{ \Carbon\Carbon::parse($detail->tanggal_kedaluwarsa)->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-stone-400">-</span>
                        @endif
                    </td>
                    <td class="td text-right font-bold text-stone-900">
                        {{ number_format($detail->jumlah) }} pcs
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="td py-8 text-center text-stone-400">
                        Tidak ada detail produk.
                    </td>
                </tr>
            @endforelse
            </tbody>
            <tfoot class="border-t-2 border-stone-200 bg-stone-50 font-semibold text-stone-800">
                <tr>
                    <td colspan="5" class="td text-right">TOTAL DITERIMA:</td>
                    <td class="td text-right font-bold text-brand-700">
                        {{ number_format($stockReceipt->total_kuantitas) }} pcs
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
