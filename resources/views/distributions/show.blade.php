@extends('layouts.app')

@php
    $isOwner = auth()->user()->isOwner();
    $privileged = $privileged ?? ($isOwner || (auth()->user()->can('distributions.create') && auth()->user()->branch?->is_pusat));
@endphp

@section('title', $privileged ? 'Detail Distribusi' : 'Penerimaan Kiriman')
@section('heading', $privileged ? 'Distribusi ' . $dist->id : 'Penerimaan Kiriman ' . $dist->id)
@section('breadcrumb', $privileged ? 'Operasional / Distribusi Stok / Detail' : 'Operasional / Penerimaan Distribusi / Detail')
@section('actions')<a href="{{ route('distributions.index') }}" class="btn-secondary">Kembali</a>@endsection
@section('content')
<div class="mb-4 grid gap-4 lg:grid-cols-3">
    <div class="card space-y-2 p-4 text-sm">
        <div class="flex justify-between"><span class="text-stone-500">Status</span>
            @php
                $headerStatus = $dist->status;
                if (! $privileged && isset($groups) && $groups->isNotEmpty()) {
                    $myLines = $groups->get(auth()->user()->branch_id) ?? collect();
                    if ($myLines->isNotEmpty() && $myLines->where('status', 'dikirim')->isEmpty()) {
                        $headerStatus = 'selesai';
                    }
                }
            @endphp
            @if ($headerStatus === 'dikirim')<span class="badge-warn">Dalam perjalanan</span>
            @elseif ($headerStatus === 'selesai')<span class="badge-ok">Selesai</span>
            @else<span class="badge-bad">Dibatalkan</span>@endif</div>
        <div class="flex justify-between"><span class="text-stone-500">Dari</span><span>{{ $dist->sourceBranch?->nama_cabang }}</span></div>
        <div class="flex justify-between"><span class="text-stone-500">Waktu kirim</span><span>{{ $dist->waktu_kirim->format('d/m/Y H:i') }}</span></div>
        <div class="flex justify-between"><span class="text-stone-500">Dibuat oleh</span><span>{{ $dist->creator?->name }}</span></div>
        @if ($dist->catatan)<div><span class="text-stone-500">Catatan:</span> {{ $dist->catatan }}</div>@endif
        @if ($dist->cancelled_at)
            <div class="rounded-lg bg-red-50 p-2 text-red-700">Dibatalkan {{ $dist->cancelled_at->format('d/m/Y H:i') }} oleh {{ $dist->cancelledBy?->name }}: {{ $dist->alasan_batal }}</div>
        @endif
    </div>
    @if ($canCancel)
        <form method="POST" action="{{ route('distributions.cancel', $dist) }}" class="card space-y-2 p-4 text-sm lg:col-span-2" data-confirm="Batalkan semua kiriman yang masih dalam perjalanan? Stok kembali ke gudang pusat.">
            @csrf
            <div class="font-medium text-brand-900">Batalkan kiriman yang belum diterima</div>
            <p class="text-stone-500">Hanya baris yang masih dalam perjalanan yang dibatalkan. Baris yang sudah dikonfirmasi tidak berubah.</p>
            <input name="alasan" required minlength="3" maxlength="150" placeholder="Alasan pembatalan" class="input">
            <button class="btn-danger" type="submit">Batalkan kiriman</button>
        </form>
    @endif
</div>

@forelse ($groups as $branchId => $lines)
    @php
        $dest = $lines->first()->destination;
        $pending = $lines->where('status', 'dikirim');
        $canConfirm = $canReceive && $pending->isNotEmpty() && (auth()->user()->isOwner() || auth()->user()->branch_id === $branchId);
    @endphp
    <div class="card mb-4 overflow-x-auto">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <div class="font-medium text-brand-900">Tujuan: {{ $dest?->nama_cabang }} @if ($dest?->is_pusat)<span class="badge-warn ml-1">alokasi penjualan Pusat</span>@endif</div>
            @if ($pending->isNotEmpty())<span class="badge-warn">{{ $pending->count() }} baris menunggu konfirmasi</span>@endif
        </div>
        @if ($canConfirm)
        <form method="POST" action="{{ route('distributions.receive', $dist) }}">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
        @endif
        <table class="min-w-full divide-y divide-stone-200">
            <thead><tr><th class="th">Produk</th><th class="th text-right">Dikirim</th><th class="th text-right">Diterima</th><th class="th text-right">Selisih</th><th class="th">Status</th><th class="th">Dikonfirmasi</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @foreach ($lines as $l)
                <tr>
                    <td class="td font-medium">{{ $l->product?->nama_produk }}</td>
                    <td class="td text-right">{{ $l->jumlah_dikirim }}</td>
                    <td class="td text-right">
                        @if ($canConfirm && $l->status === 'dikirim')
                            <input type="number" min="0" max="{{ $l->jumlah_dikirim }}" name="terima[{{ $l->id }}]" value="{{ old("terima.{$l->id}", $l->jumlah_dikirim) }}" class="input w-24 text-right" required>
                        @else{{ $l->jumlah_diterima ?? '-' }}@endif
                    </td>
                    <td class="td text-right {{ ($l->selisih ?? 0) > 0 ? 'font-semibold text-red-600' : '' }}">{{ $l->selisih ?? '-' }}</td>
                    <td class="td">
                        @if ($l->status === 'dikirim')<span class="badge-warn">Dalam perjalanan</span>
                        @elseif ($l->status === 'diterima')<span class="badge-ok">Diterima</span>
                        @else<span class="badge-bad">Dibatalkan</span>@endif
                    </td>
                    <td class="td text-xs">@if ($l->waktu_diterima){{ $l->waktu_diterima->format('d/m/Y H:i') }} · {{ $l->receiver?->name }}@if ($l->catatan_selisih)<br><span class="text-stone-500">{{ $l->catatan_selisih }}</span>@endif @else - @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if ($canConfirm)
            <div class="flex flex-wrap items-center gap-3 border-t border-stone-200 p-4">
                <input name="catatan" value="{{ old('catatan') }}" maxlength="150" placeholder="Catatan (wajib bila ada selisih)" class="input max-w-md">
                <button type="submit" class="btn-primary">Konfirmasi penerimaan</button>
                <span class="text-xs text-stone-500">Jumlah yang diterima masuk ke stok jual {{ $dest?->nama_cabang }}. Selisih tidak masuk stok mana pun.</span>
            </div>
        </form>
        @endif
    </div>
@empty
    <div class="card p-10 text-center text-stone-400">Tidak ada baris distribusi yang dapat Anda lihat.</div>
@endforelse
@endsection
