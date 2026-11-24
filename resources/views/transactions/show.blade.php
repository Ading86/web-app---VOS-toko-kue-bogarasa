@extends('layouts.app')
@section('title', 'Detail Transaksi')
@section('heading', 'Transaksi ' . $trx->kode_transaksi)
@section('breadcrumb', 'Operasional / Transaksi / Detail')
@section('actions')
    <a href="{{ route('transactions.receipt', $trx) }}" target="_blank" class="btn-secondary">Cetak struk</a>
    <a href="{{ route('transactions.index') }}" class="btn-secondary">Kembali</a>
@endsection
@section('content')
<div class="grid gap-4 lg:grid-cols-3">
    <div class="card overflow-x-auto lg:col-span-2">
        <table class="min-w-full divide-y divide-stone-200">
            <thead><tr><th class="th">Produk</th><th class="th text-right">Harga</th><th class="th text-right">Jumlah</th><th class="th text-right">Subtotal</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @foreach ($trx->details as $d)
                <tr><td class="td">{{ $d->nama_produk }}</td><td class="td text-right">Rp {{ number_format($d->harga_satuan, 0, ',', '.') }}</td>
                    <td class="td text-right">{{ $d->jumlah }}</td><td class="td text-right">Rp {{ number_format($d->subtotal, 0, ',', '.') }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="3" class="td text-right font-semibold">Total</td><td class="td text-right font-bold">Rp {{ number_format($trx->total, 0, ',', '.') }}</td></tr></tfoot>
        </table>
    </div>
    <div class="space-y-4">
        <div class="card space-y-2 p-4 text-sm">
            <div class="flex justify-between"><span class="text-stone-500">Status</span><span class="{{ $trx->status === 'selesai' ? 'badge-ok' : 'badge-bad' }}">{{ ucfirst($trx->status) }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Cabang</span><span>{{ $trx->branch?->nama_cabang }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Kasir</span><span>{{ $trx->cashier?->name }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Waktu</span><span>{{ $trx->waktu_transaksi->format('d/m/Y H:i') }}</span></div>
            @foreach ($trx->payments as $p)
                <div class="flex justify-between"><span class="text-stone-500">Pembayaran</span><span>{{ strtoupper($p->metode) }} · Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</span></div>
                @if ($p->kembalian > 0)<div class="flex justify-between"><span class="text-stone-500">Kembalian</span><span>Rp {{ number_format($p->kembalian, 0, ',', '.') }}</span></div>@endif
                @if ($p->referensi)<div class="flex justify-between"><span class="text-stone-500">Referensi</span><span>{{ $p->referensi }}</span></div>@endif
            @endforeach
            @if ($trx->status === 'dibatalkan')
                <div class="border-t pt-2 text-red-700">Dibatalkan oleh {{ $trx->cancelledBy?->name }} pada {{ $trx->cancelled_at?->format('d/m/Y H:i') }}.<br>Alasan: {{ $trx->alasan_batal }}</div>
            @endif
        </div>
        @can('transactions.cancel')
            @if ($trx->status === 'selesai')
                <form method="POST" action="{{ route('transactions.cancel', $trx) }}" class="card space-y-3 p-4" data-confirm="Batalkan transaksi ini? Stok akan dikembalikan.">
                    @csrf
                    <label class="label">Alasan pembatalan</label>
                    <input name="alasan" class="input" required minlength="3" maxlength="255">
                    <button class="btn-danger w-full" type="submit">Batalkan transaksi</button>
                </form>
            @endif
        @endcan
    </div>
</div>
@endsection
