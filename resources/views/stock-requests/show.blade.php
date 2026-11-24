@extends('layouts.app')

@php
    $u = auth()->user();
    $isOwner = $u->isOwner();
    $isKasir = $u->isKasir();
    $isWarehouse = $u->isWarehouse();
    $isTargetBranch = $u->branch_id === $request->branch_id;
@endphp

@section('title', 'Detail Permintaan ' . $request->id)
@section('heading', 'Permintaan Stok ' . $request->id)
@section('breadcrumb', 'Operasional / Permintaan Stok / ' . $request->id)
@section('actions')
    <a href="{{ route('stock-requests.index') }}" class="btn-secondary">Kembali</a>
@endsection

@section('content')
{{-- Timeline Alur Status --}}
<div class="card mb-5 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
        {{-- Step 1: Diajukan --}}
        <div class="p-3 rounded-xl border {{ $request->status === 'diajukan' ? 'border-amber-400 bg-amber-50/80 font-bold text-amber-900 shadow-sm ring-2 ring-amber-200' : 'border-stone-200 bg-stone-50 text-stone-500' }}">
            <div class="flex items-center justify-center gap-1.5 mb-1">
                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs {{ $request->status === 'diajukan' ? 'bg-amber-600 text-white' : 'bg-stone-300 text-stone-700' }}">1</span>
                <span class="text-xs uppercase tracking-wider">Pengajuan</span>
            </div>
            <div class="text-sm font-semibold">Cabang Mengajukan</div>
            <div class="text-[11px] text-stone-500 mt-0.5">{{ $request->tanggal_permintaan?->format('d/m/Y') }}</div>
        </div>

        {{-- Step 2: Disetujui / Ditolak --}}
        <div class="p-3 rounded-xl border {{ $request->isApproved() ? 'border-blue-400 bg-blue-50/80 font-bold text-blue-900 shadow-sm ring-2 ring-blue-200' : ($request->isRejected() ? 'border-red-400 bg-red-50 text-red-900 font-bold ring-2 ring-red-200' : 'border-stone-200 bg-stone-50 text-stone-500') }}">
            <div class="flex items-center justify-center gap-1.5 mb-1">
                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs {{ $request->isApproved() ? 'bg-blue-600 text-white' : ($request->isRejected() ? 'bg-red-600 text-white' : 'bg-stone-300 text-stone-700') }}">2</span>
                <span class="text-xs uppercase tracking-wider">Review Owner</span>
            </div>
            <div class="text-sm font-semibold">
                @if ($request->isRejected()) Ditolak Owner @else Persetujuan Owner @endif
            </div>
            <div class="text-[11px] text-stone-500 mt-0.5">
                {{ $request->approved_at ? $request->approved_at->format('d/m/Y H:i') : ($request->isPending() ? 'Menunggu Review' : '-') }}
            </div>
        </div>

        {{-- Step 3: Dikirim oleh Warehouse --}}
        <div class="p-3 rounded-xl border {{ $request->isDispatched() ? 'border-amber-400 bg-amber-50/80 font-bold text-amber-900 shadow-sm ring-2 ring-amber-200' : 'border-stone-200 bg-stone-50 text-stone-500' }}">
            <div class="flex items-center justify-center gap-1.5 mb-1">
                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs {{ $request->isDispatched() ? 'bg-amber-600 text-white' : 'bg-stone-300 text-stone-700' }}">3</span>
                <span class="text-xs uppercase tracking-wider">Pengiriman</span>
            </div>
            <div class="text-sm font-semibold">Proses Warehouse</div>
            <div class="text-[11px] text-stone-500 mt-0.5">
                {{ $request->processed_at ? $request->processed_at->format('d/m/Y H:i') : ($request->isApproved() ? 'Siap Dikirim' : '-') }}
            </div>
        </div>

        {{-- Step 4: Selesai Diterima Cabang --}}
        <div class="p-3 rounded-xl border {{ $request->isCompleted() ? 'border-emerald-400 bg-emerald-50/80 font-bold text-emerald-900 shadow-sm ring-2 ring-emerald-200' : 'border-stone-200 bg-stone-50 text-stone-500' }}">
            <div class="flex items-center justify-center gap-1.5 mb-1">
                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs {{ $request->isCompleted() ? 'bg-emerald-600 text-white' : 'bg-stone-300 text-stone-700' }}">4</span>
                <span class="text-xs uppercase tracking-wider">Selesai</span>
            </div>
            <div class="text-sm font-semibold">Diterima Cabang</div>
            <div class="text-[11px] text-stone-500 mt-0.5">
                {{ $request->isCompleted() ? 'Kue Siap Jual' : '-' }}
            </div>
        </div>
    </div>
</div>

{{-- Kartu Ringkasan Informasi --}}
<div class="mb-5 grid gap-4 lg:grid-cols-3">
    {{-- Info Pengajuan Cabang --}}
    <div class="card p-4 text-sm space-y-2">
        <div class="flex items-center justify-between border-b border-stone-200 pb-2">
            <span class="font-bold text-stone-800">Informasi Cabang</span>
            <span class="font-mono text-xs text-stone-500">{{ $request->branch?->kode_cabang }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Cabang Pemohon:</span>
            <span class="font-semibold text-stone-800">{{ $request->branch?->nama_cabang }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Diajukan Oleh:</span>
            <span class="text-stone-700">{{ $request->requester?->name ?? '-' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Tanggal Pengajuan:</span>
            <span class="text-stone-700">{{ $request->tanggal_permintaan?->format('d/m/Y') }}</span>
        </div>
        @if ($request->catatan_cabang)
            <div class="pt-1 border-t border-stone-100">
                <span class="text-xs font-semibold text-stone-500">Catatan Cabang:</span>
                <p class="text-xs text-stone-700 bg-stone-50 p-2 rounded-lg mt-0.5 italic">
                    "{{ $request->catatan_cabang }}"
                </p>
            </div>
        @endif
    </div>

    {{-- Info Persetujuan Owner --}}
    <div class="card p-4 text-sm space-y-2">
        <div class="flex items-center justify-between border-b border-stone-200 pb-2">
            <span class="font-bold text-stone-800">Status Persetujuan Owner</span>
            @if ($request->isApproved())
                <span class="badge-ok text-[11px]">Disetujui</span>
            @elseif ($request->isRejected())
                <span class="badge-bad text-[11px]">Ditolak</span>
            @else
                <span class="badge-warn text-[11px]">Menunggu Review</span>
            @endif
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Ditinjau Oleh:</span>
            <span class="font-medium text-stone-800">{{ $request->approver?->name ?? '-' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Waktu Peninjauan:</span>
            <span class="text-stone-700">{{ $request->approved_at ? $request->approved_at->format('d/m/Y H:i') : '-' }}</span>
        </div>
        @if ($request->catatan_owner)
            <div class="pt-1 border-t border-stone-100">
                <span class="text-xs font-semibold text-stone-500">{{ $request->isRejected() ? 'Alasan Penolakan:' : 'Catatan Owner:' }}</span>
                <p class="text-xs {{ $request->isRejected() ? 'text-red-700 bg-red-50' : 'text-stone-700 bg-stone-50' }} p-2 rounded-lg mt-0.5 italic">
                    "{{ $request->catatan_owner }}"
                </p>
            </div>
        @endif
    </div>

    {{-- Info Pemrosesan Warehouse & Pengiriman --}}
    <div class="card p-4 text-sm space-y-2">
        <div class="flex items-center justify-between border-b border-stone-200 pb-2">
            <span class="font-bold text-stone-800">Status Gudang & Pengiriman</span>
            @if ($request->isCompleted())
                <span class="badge-ok text-[11px]">Selesai Diterima</span>
            @elseif ($request->isDispatched())
                <span class="badge-warn text-[11px]">Dalam Perjalanan</span>
            @else
                <span class="badge-mute text-[11px]">Belum Diproses</span>
            @endif
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Petugas Warehouse:</span>
            <span class="font-medium text-stone-800">{{ $request->processor?->name ?? '-' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-stone-500">Waktu Pengiriman:</span>
            <span class="text-stone-700">{{ $request->processed_at ? $request->processed_at->format('d/m/Y H:i') : '-' }}</span>
        </div>
        @if ($request->distribution_id)
            <div class="flex justify-between items-center pt-1 border-t border-stone-100">
                <span class="text-stone-500">No. Distribusi:</span>
                <a href="{{ route('distributions.show', $request->distribution_id) }}" class="text-brand-800 font-mono font-bold hover:underline">
                    {{ $request->distribution_id }} &rarr;
                </a>
            </div>
        @endif
    </div>
</div>

{{-- FORM / TABEL DETAIL PRODUK --}}
<div class="card overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-stone-200 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="font-bold text-stone-900 text-base">Daftar Produk Permintaan</h3>
            <p class="text-xs text-stone-500">Rincian perbandingan kuantitas kue pada setiap tahapan distribusi WMS.</p>
        </div>
        @if ($request->isPending())
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                Tahap: Review & Persetujuan Kuantitas
            </span>
        @elseif ($request->isApproved())
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                Tahap: Pemrosesan & Pengiriman Gudang
            </span>
        @elseif ($request->isDispatched())
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                Tahap: Menunggu Konfirmasi Penerimaan Cabang
            </span>
        @endif
    </div>

    {{-- KONDISI 1: FORM REVIEW & PERSETUJUAN OWNER --}}
    @if ($isOwner && $request->isPending())
        <form method="POST" action="{{ route('stock-requests.approve', $request) }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-left">
                    <thead>
                        <tr class="bg-stone-50 border-b border-stone-200">
                            <th class="th py-3 px-4">Produk Kue</th>
                            <th class="th py-3 px-3 text-center">Stok Gudang Pusat</th>
                            <th class="th py-3 px-3 text-center">Stok Cabang Saat Ini</th>
                            <th class="th py-3 px-3 text-center font-bold">Kuantitas Diminta</th>
                            <th class="th py-3 px-4 text-center font-bold bg-amber-100/40 text-amber-900 border-x border-amber-200/80">
                                Kuantitas Disetujui <span class="text-red-500">*</span>
                                <span class="block text-[10px] font-normal uppercase text-amber-800">Toggle untuk Setujui/Tolak</span>
                                <div class="flex items-center justify-center gap-2 mt-1.5">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" id="toggleAll" class="toggle toggle-primary h-5 w-5" />
                                        <span class="text-xs font-medium text-stone-700">Semua</span>
                                    </label>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 bg-white">
                    @foreach ($request->details as $d)
                        @php
                            $stkGudang = (int) ($gudangStocks[$d->product_id] ?? 0);
                            $stkCabang = (int) ($cabangStocks[$d->product_id] ?? 0);
                        @endphp
                        <tr class="hover:bg-amber-50/20">
                            {{-- Produk --}}
                            <td class="td py-3 px-4">
                                <div class="font-semibold text-stone-900">{{ $d->product?->nama_produk }}</div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs font-mono text-stone-400">{{ $d->product?->kode_produk }}</span>
                                    @if ($d->product?->tipe_kue === 'basah')
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20">Basah</span>
                                    @elseif ($d->product?->tipe_kue === 'kering')
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/25">Kering</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Stok Gudang Pusat --}}
                            <td class="td py-3 px-3 text-center align-middle">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $stkGudang >= $d->jumlah_minta ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $stkGudang }} pcs
                                </span>
                            </td>

                            {{-- Stok Cabang --}}
                            <td class="td py-3 px-3 text-center align-middle text-stone-600 font-semibold text-xs">
                                {{ $stkCabang }} pcs
                            </td>

                            {{-- Kuantitas Diminta --}}
                            <td class="td py-3 px-3 text-center align-middle font-bold text-stone-800">
                                {{ $d->jumlah_minta }} pcs
                            </td>

                            {{-- Toggle Kuantitas Disetujui --}}
                            <td class="td py-2.5 px-4 text-center align-middle bg-amber-50/20 border-x border-amber-200/40">
                                <div class="flex items-center justify-center gap-2">
                                    <input type="hidden"
                                           name="approved_qtys[{{ $d->product_id }}]"
                                           value="{{ $d->jumlah_minta }}"
                                           data-full-qty="{{ $d->jumlah_minta }}">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                               class="item-toggle toggle toggle-primary h-5 w-5"
                                               data-product-id="{{ $d->product_id }}"
                                               checked>
                                        <span class="text-sm font-semibold text-stone-700">
                                            <span class="qty-text">{{ $d->jumlah_minta }}</span> pcs
                                        </span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Panel Aksi Owner: Setujui atau Tolak --}}
            <div class="p-5 bg-stone-50 border-t border-stone-200 space-y-4">
                <div>
                    <label class="label font-semibold text-stone-700">Catatan Owner/Kasir (Opsional)</label>
                    <input name="catatan_owner"
                           value="{{ old('catatan_owner') }}"
                           placeholder="Misal: Stok Nastar dikurangi karena kuota dibagi dengan cabang lain, dsb."
                           class="input max-w-xl">
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" class="btn-primary flex items-center gap-2">
                        <x-icon name="check-circle" class="h-4 w-4" />
                        <span>Setujui Permintaan Stok</span>
                    </button>

                    <button type="button"
                            onclick="document.getElementById('rejectModal').classList.remove('hidden')"
                            class="btn-danger flex items-center gap-2">
                        <x-icon name="x-circle" class="h-4 w-4" />
                        <span>Tolak Permintaan</span>
                    </button>
                </div>
            </div>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleAll = document.getElementById('toggleAll');
            const itemToggles = document.querySelectorAll('.item-toggle');

            // Toggle all handler
            toggleAll.addEventListener('change', function() {
                itemToggles.forEach(toggle => {
                    toggle.checked = this.checked;
                    updateQtyInput(toggle);
                });
            });

            // Individual toggle handler
            itemToggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    updateQtyInput(this);
                    // Update toggleAll state
                    const allChecked = [...itemToggles].every(t => t.checked);
                    const allUnchecked = [...itemToggles].every(t => !t.checked);
                    toggleAll.checked = allChecked;
                    toggleAll.indeterminate = !allChecked && !allUnchecked;
                });
            });

            function updateQtyInput(toggle) {
                const productId = toggle.dataset.productId;
                const fullQty = toggle.closest('td').querySelector('input[name^="approved_qtys"]').dataset.fullQty;
                const hiddenInput = document.querySelector(`input[name="approved_qtys[${productId}]"]`);
                const qtyText = toggle.closest('label').querySelector('.qty-text');
                
                if (toggle.checked) {
                    hiddenInput.value = fullQty;
                    qtyText.textContent = fullQty;
                } else {
                    hiddenInput.value = 0;
                    qtyText.textContent = 0;
                }
            }
        });
        </script>

        {{-- Modal Tolak Permintaan --}}
        <div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 backdrop-blur-sm p-4">
            <div class="card max-w-md w-full p-5 space-y-4 bg-white shadow-xl">
                <div class="flex items-center gap-2 text-red-600 font-bold text-base">
                    <x-icon name="alert" class="h-5 w-5" />
                    <span>Tolak Permintaan Stok</span>
                </div>
                <p class="text-sm text-stone-600">
                    Masukkan alasan penolakan permintaan stok ini. Catatan akan dikirimkan kepada cabang pemohon.
                </p>
                <form method="POST" action="{{ route('stock-requests.reject', $request) }}">
                    @csrf
                    <div>
                        <label class="label font-semibold text-stone-700">Alasan Penolakan <span class="text-red-500">*</span></label>
                        <textarea name="alasan_penolakan"
                                  rows="3"
                                  required
                                  placeholder="Misal: Stok di gudang pusat sedang kosong, produksi libur, dsb."
                                  class="input">{{ old('alasan_penolakan') }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button"
                                onclick="document.getElementById('rejectModal').classList.add('hidden')"
                                class="btn-secondary">
                            Batal
                        </button>
                        <button type="submit" class="btn-danger">
                            Konfirmasi Tolak
                        </button>
                    </div>
                </form>
            </div>
        </div>

    {{-- KONDISI 3: TABEL READ-ONLY (Daftar rincian standar) --}}
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-left">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200">
                        <th class="th py-3 px-4">Produk Kue</th>
                        @if ($isOwner || $isKasir || $isWarehouse)
                            <th class="th py-3 px-3 text-center">Stok Gudang Pusat</th>
                        @endif
                        <th class="th py-3 px-3 text-center">Stok Cabang</th>
                        <th class="th py-3 px-3 text-center">Diminta</th>
                        <th class="th py-3 px-3 text-center">Disetujui</th>
                        <th class="th py-3 px-3 text-center">Dikirim</th>
                        <th class="th py-3 px-3 text-center">Diterima</th>
                        <th class="th py-3 px-4 text-center">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 bg-white">
                @foreach ($request->details as $d)
                    @php
                        $stkGudang = (int) ($gudangStocks[$d->product_id] ?? 0);
                        $stkCabang = (int) ($cabangStocks[$d->product_id] ?? 0);
                        $selisih = $d->selisih;
                    @endphp
                    <tr class="hover:bg-stone-50/50">
                        {{-- Produk --}}
                        <td class="td py-3 px-4">
                            <div class="font-semibold text-stone-900">{{ $d->product?->nama_produk }}</div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-xs font-mono text-stone-400">{{ $d->product?->kode_produk }}</span>
                                @if ($d->product?->tipe_kue === 'basah')
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-blue-50 text-blue-700">Basah</span>
                                @elseif ($d->product?->tipe_kue === 'kering')
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-50 text-amber-700">Kering</span>
                                @endif
                            </div>
                        </td>

                        {{-- Stok Gudang Pusat --}}
                        @if ($isOwner || $isKasir || $isWarehouse)
                            <td class="td py-3 px-3 text-center align-middle font-semibold text-xs text-stone-700">
                                {{ $stkGudang }} pcs
                            </td>
                        @endif

                        {{-- Stok Cabang --}}
                        <td class="td py-3 px-3 text-center align-middle text-xs text-stone-600">
                            {{ $stkCabang }} pcs
                        </td>

                        {{-- Diminta --}}
                        <td class="td py-3 px-3 text-center align-middle font-bold text-stone-800">
                            {{ $d->jumlah_minta }} pcs
                        </td>

                        {{-- Disetujui --}}
                        <td class="td py-3 px-3 text-center align-middle font-bold text-blue-900">
                            @if ($request->isPending())
                                <span class="text-stone-400 font-normal">Menunggu</span>
                            @elseif ($request->isRejected())
                                <span class="text-red-500">0</span>
                            @else
                                {{ $d->jumlah_setuju }} pcs
                            @endif
                        </td>

                        {{-- Dikirim --}}
                        <td class="td py-3 px-3 text-center align-middle text-amber-900 font-semibold">
                            {{ $d->jumlah_kirim !== null ? $d->jumlah_kirim . ' pcs' : '-' }}
                        </td>

                        {{-- Diterima --}}
                        <td class="td py-3 px-3 text-center align-middle text-emerald-800 font-semibold">
                            {{ $d->jumlah_terima !== null ? $d->jumlah_terima . ' pcs' : '-' }}
                        </td>

                        {{-- Selisih --}}
                        <td class="td py-3 px-4 text-center align-middle">
                            @if ($selisih !== null && $selisih > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700" title="{{ $d->catatan_selisih }}">
                                    Kurang {{ $selisih }}
                                </span>
                                @if ($d->catatan_selisih)
                                    <div class="text-[10px] text-stone-500 italic mt-0.5">({{ $d->catatan_selisih }})</div>
                                @endif
                            @elseif ($selisih === 0)
                                <span class="text-xs text-emerald-600 font-bold">Pas (0)</span>
                            @else
                                <span class="text-xs text-stone-400">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- AKSI KHUSUS OWNER (Saat status disetujui) --}}
        @if ($isOwner && $request->isApproved())
            <div class="p-5 bg-amber-50/60 border-t border-amber-200">
                <form method="POST" action="{{ route('stock-requests.dispatch', $request) }}" class="space-y-4">
                    @csrf
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-xl bg-amber-100 text-amber-800">
                            <x-icon name="truck" class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="font-bold text-stone-900 text-sm">Pemrosesan & Pengiriman</h4>
                            <p class="text-xs text-stone-600">
                                Permintaan ini telah disetujui. Klik tombol di bawah untuk memproses dan mengirimkan kue fisik ke cabang <strong>{{ $request->branch?->nama_cabang }}</strong>. Stok Gudang Pusat akan dipotong secara FIFO dan status beralih menjadi <em>Dalam Perjalanan</em>.
                            </p>
                        </div>
                    </div>

                    <div class="max-w-xl">
                        <label class="label font-semibold text-stone-700">Catatan Pengiriman (Opsional)</label>
                        <input name="catatan_pengiriman"
                               value="{{ old('catatan_pengiriman') }}"
                               placeholder="Misal: Dikirim via kurir internal pukul 08:30 WIB, no. armada B 1234 CD"
                               class="input">
                    </div>

                    <div class="pt-1">
                        <button type="submit" class="btn-primary flex items-center gap-2">
                            <x-icon name="truck" class="h-4 w-4" />
                            <span>Proses & Kirimkan ke Cabang</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
@endsection