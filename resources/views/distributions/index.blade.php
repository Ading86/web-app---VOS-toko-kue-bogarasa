@extends('layouts.app')

@php
    $isOwner = auth()->user()->isOwner();
    $userBranch = auth()->user()->branch;
    $isPrivileged = $isPrivileged ?? ($isOwner || (auth()->user()->can('distributions.create') && $userBranch?->is_pusat));
@endphp

@section('title', $isPrivileged ? 'Distribusi Stok' : 'Penerimaan Distribusi')
@section('heading', $isPrivileged ? 'Distribusi Stok' : 'Penerimaan Distribusi Stok')
@section('subtitle', $isPrivileged ? 'Pencatatan dan pengiriman stok kue dari Gudang Pusat ke cabang-cabang.' : 'Daftar pengiriman stok dari Gudang Pusat ke ' . ($userBranch?->nama_cabang ?? 'cabang Anda') . '. Konfirmasi penerimaan agar stok masuk ke penjualan toko.')
@section('breadcrumb', $isPrivileged ? 'Operasional / Distribusi Stok' : 'Operasional / Penerimaan Distribusi')

@section('actions')
    @can('distributions.create')
        <a href="{{ route('distributions.create') }}" class="btn-primary flex items-center gap-2">
            <span>Distribusi Baru</span>
        </a>
    @endcan
@endsection

@section('content')
@if (! $isPrivileged)
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-stone-700 flex items-center gap-2.5">
        <x-icon name="info" class="h-4 w-4 text-amber-800 shrink-0" />
        <span>
            Kue yang dikirim oleh Owner / Gudang Pusat akan berstatus <strong>Dalam perjalanan</strong>. Silakan klik <strong>Konfirmasi Terima</strong> untuk mencocokkan jumlah fisik yang tiba dan memasukkannya ke stok kasir.
        </span>
    </div>
@endif

<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-5">
    <input name="search" value="{{ request('search') }}" placeholder="Cari nomor (mis. DST0001)" class="input">
    <select name="status" class="input">
        <option value="">Semua status</option>
        @foreach (['dikirim' => 'Dalam perjalanan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $k => $v)
            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
        @endforeach
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
    <button class="btn-secondary" type="submit">Terapkan</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead>
            <tr>
                <th class="th">Nomor</th>
                <th class="th">Waktu Kirim</th>
                <th class="th">Asal Kiriman</th>
                <th class="th text-right">Total Baris</th>
                <th class="th text-right">Menunggu</th>
                <th class="th">Status</th>
                <th class="th">Pengirim</th>
                <th class="th text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($distributions as $d)
            <tr class="hover:bg-amber-50/20 transition-colors">
                <td class="td font-mono text-xs font-bold text-stone-900">{{ $d->id }}</td>
                <td class="td text-stone-700">{{ $d->waktu_kirim->format('d/m/Y H:i') }}</td>
                <td class="td font-medium text-stone-800">{{ $d->sourceBranch?->nama_cabang }}</td>
                <td class="td text-right font-semibold">{{ $d->details_count }}</td>
                <td class="td text-right">
                    @if ($d->pending_count > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                            {{ $d->pending_count }} baris
                        </span>
                    @else
                        <span class="text-xs text-stone-400">0</span>
                    @endif
                </td>
                <td class="td">
                    @php
                        $badgeStatus = $d->status;
                        if (! $isPrivileged && $d->pending_count === 0) {
                            $badgeStatus = 'selesai';
                        }
                    @endphp
                    @if ($badgeStatus === 'dikirim')
                        <span class="badge-warn">Dalam perjalanan</span>
                    @elseif ($badgeStatus === 'selesai')
                        <span class="badge-ok">Selesai</span>
                    @else
                        <span class="badge-bad">Dibatalkan</span>
                    @endif
                </td>
                <td class="td text-stone-600">{{ $d->creator?->name }}</td>
                <td class="td text-right">
                    @if (! $isPrivileged && $d->pending_count > 0 && $d->status === 'dikirim')
                        <a href="{{ route('distributions.show', $d) }}" class="btn-primary btn-sm inline-flex items-center gap-1.5">
                            <x-icon name="check" class="h-3.5 w-3.5" />
                            <span>Konfirmasi Terima</span>
                        </a>
                    @else
                        <a href="{{ route('distributions.show', $d) }}" class="btn-secondary btn-sm">Detail</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="td py-10 text-center text-stone-400">
                    Belum ada riwayat distribusi stok.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $distributions->links() }}</div>
@endsection
