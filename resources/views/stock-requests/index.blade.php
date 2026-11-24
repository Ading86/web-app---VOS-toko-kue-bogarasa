@extends('layouts.app')

@php
    $u = auth()->user();
    $isOwner = $u->isOwner();
    $isKasir = $u->isKasir();
    $isWarehouse = $u->isWarehouse();
@endphp

@section('title', 'Permintaan Stok (WMS)')
@section('heading', 'Permintaan Stok Cabang')
@section('subtitle', $isOwner
    ? 'Kelola dan setujui pengajuan permintaan stok kue dari seluruh cabang.'
    : ($isWarehouse
        ? 'Daftar permintaan stok yang telah disetujui dan siap diproses kirim ke cabang.'
        : 'Ajukan permintaan stok kue ke Gudang Pusat dan pantau status persetujuan serta pengirimannya.'))
@section('breadcrumb', 'Operasional / Permintaan Stok')

@section('actions')
    @can('stock_requests.create')
        <a href="{{ route('stock-requests.create') }}" class="btn-primary flex items-center gap-2">
            <span>Ajukan Permintaan Stok</span>
        </a>
    @endcan
@endsection

@section('content')
{{-- Filter & Pencarian --}}
<form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
    <div class="lg:col-span-2">
        <input name="search"
               value="{{ request('search') }}"
               placeholder="Cari ID (mis. SRQ0001) / catatan..."
               class="input">
    </div>

    <div>
        <select name="status" class="input">
            <option value="">Semua Status</option>
            <option value="diajukan" @selected(request('status') === 'diajukan')>Menunggu Persetujuan</option>
            <option value="disetujui" @selected(request('status') === 'disetujui')>Disetujui (Siap Kirim)</option>
            <option value="dikirim" @selected(request('status') === 'dikirim')>Dalam Perjalanan</option>
            <option value="selesai" @selected(request('status') === 'selesai')>Selesai Diterima</option>
            <option value="ditolak" @selected(request('status') === 'ditolak')>Ditolak Owner</option>
        </select>
    </div>

    @if (($isOwner || $isWarehouse) && $branches->isNotEmpty())
        <div>
            <select name="branch_id" class="input">
                <option value="">Semua Cabang</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id_cabang }}" @selected(request('branch_id') === $b->id_cabang)>
                        {{ $b->nama_cabang }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="input" title="Dari tanggal">
    </div>

    <div>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="input" title="Sampai tanggal">
    </div>

    <div class="sm:col-span-2 lg:col-span-6 flex gap-2 justify-end">
        <button class="btn-primary" type="submit">Terapkan Filter</button>
        <a href="{{ route('stock-requests.index') }}" class="btn-secondary">Reset</a>
    </div>
</form>

{{-- Tabel Daftar Permintaan --}}
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left border-collapse">
            <thead>
                <tr class="bg-stone-50 border-b border-stone-200">
                    <th class="th py-3.5 px-4 font-semibold text-stone-700">No. Permintaan</th>
                    <th class="th py-3.5 px-3 font-semibold text-stone-700">Tanggal</th>
                    <th class="th py-3.5 px-3 font-semibold text-stone-700">Cabang Pemohon</th>
                    <th class="th py-3.5 px-3 font-semibold text-stone-700">Pemohon</th>
                    <th class="th py-3.5 px-3 text-center font-semibold text-stone-700">Total Item</th>
                    <th class="th py-3.5 px-3 text-center font-semibold text-stone-700">Status</th>
                    <th class="th py-3.5 px-4 text-right font-semibold text-stone-700">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100 bg-white">
            @forelse ($requests as $r)
                <tr class="hover:bg-amber-50/20 transition-colors">
                    {{-- ID Permintaan --}}
                    <td class="td py-3 px-4 font-mono text-xs font-bold text-stone-900">
                        <a href="{{ route('stock-requests.show', $r) }}" class="text-brand-800 hover:text-brand-900 underline decoration-amber-300">
                            {{ $r->id }}
                        </a>
                    </td>

                    {{-- Tanggal Permintaan --}}
                    <td class="td py-3 px-3 text-stone-700 whitespace-nowrap">
                        {{ $r->tanggal_permintaan?->format('d/m/Y') }}
                    </td>

                    {{-- Cabang --}}
                    <td class="td py-3 px-3">
                        <span class="font-semibold text-stone-800">{{ $r->branch?->nama_cabang }}</span>
                        @if ($r->branch?->is_pusat)
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                Pusat
                            </span>
                        @endif
                    </td>

                    {{-- Pemohon --}}
                    <td class="td py-3 px-3 text-xs text-stone-600">
                        {{ $r->requester?->name ?? '-' }}
                    </td>

                    {{-- Total Produk --}}
                    <td class="td py-3 px-3 text-center font-semibold text-stone-700">
                        {{ $r->details_count }} jenis kue
                    </td>

                    {{-- Status Badge --}}
                    <td class="td py-3 px-3 text-center">
                        @if ($r->status === 'diajukan')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300/80">
                                Menunggu Review
                            </span>
                        @elseif ($r->status === 'disetujui')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300/80">
                                Disetujui (Siap Kirim)
                            </span>
                        @elseif ($r->status === 'dikirim')
                            <span class="badge-warn font-bold">
                                Dalam Perjalanan
                            </span>
                        @elseif ($r->status === 'selesai')
                            <span class="badge-ok font-bold">
                                Selesai Diterima
                            </span>
                        @elseif ($r->status === 'ditolak')
                            <span class="badge-bad font-bold">
                                Ditolak Owner
                            </span>
                        @else
                            <span class="badge-mute">
                                {{ ucfirst($r->status) }}
                            </span>
                        @endif
                    </td>

                    {{-- Aksi --}}
                    <td class="td py-3 px-4 text-right">
                        @if ($isOwner && $r->isPending())
                            <a href="{{ route('stock-requests.show', $r) }}" class="btn-primary btn-sm inline-flex items-center gap-1">
                                <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                <span>Review & Setujui</span>
                            </a>
                        @elseif ($isOwner && $r->isApproved())
                            <a href="{{ route('stock-requests.show', $r) }}" class="btn-primary btn-sm inline-flex items-center gap-1">
                                <x-icon name="truck" class="h-3.5 w-3.5" />
                                <span>Proses Kirim</span>
                            </a>
                        @elseif ((! $isOwner && ! $isKasir) && $r->isDispatched())
                            <a href="{{ route('stock-requests.show', $r) }}" class="btn-primary btn-sm inline-flex items-center gap-1">
                                <x-icon name="check" class="h-3.5 w-3.5" />
                                <span>Konfirmasi Terima</span>
                            </a>
                        @else
                            <a href="{{ route('stock-requests.show', $r) }}" class="btn-secondary btn-sm">
                                Detail
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="td py-12 text-center text-stone-400">
                        <x-icon name="inbox" class="mx-auto mb-2 h-8 w-8 text-stone-300" />
                        Belum ada riwayat permintaan stok kue.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $requests->links() }}</div>
@endsection
