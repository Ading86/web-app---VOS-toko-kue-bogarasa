@extends('layouts.app')
@section('title', 'Transaksi')
@section('heading', auth()->user()->isOwner() ? 'Transaksi' : 'Riwayat Transaksi')
@section('breadcrumb', 'Operasional / Transaksi')
@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-6">
    <input name="search" value="{{ request('search') }}" placeholder="Kode transaksi" class="input lg:col-span-2">
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
    <select name="status" class="input"><option value="">Semua status</option>
        <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
        <option value="dibatalkan" @selected(request('status') === 'dibatalkan')>Dibatalkan</option></select>
    @if ($branches->isNotEmpty())
        <select name="branch_id" class="input"><option value="">Semua cabang</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select>
    @endif
    <button class="btn-secondary" type="submit">Terapkan</button>
</form>

<div class="mb-4 grid gap-3 sm:grid-cols-2">
    <div class="card p-4"><div class="text-xs text-stone-500">Omzet (transaksi selesai, sesuai filter)</div><div class="text-xl font-bold text-brand-900">Rp {{ number_format($sumOmzet, 0, ',', '.') }}</div></div>
    <div class="card p-4"><div class="text-xs text-stone-500">Jumlah transaksi selesai</div><div class="text-xl font-bold text-brand-900">{{ number_format($sumCount, 0, ',', '.') }}</div></div>
</div>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Kode</th><th class="th">Waktu</th><th class="th">Cabang</th><th class="th">Kasir</th><th class="th text-right">Total</th><th class="th">Status</th><th class="th text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($transactions as $t)
            <tr>
                <td class="td font-mono text-xs">{{ $t->kode_transaksi }}</td>
                <td class="td">{{ $t->waktu_transaksi->format('d/m/Y H:i') }}</td>
                <td class="td">{{ $t->branch?->nama_cabang }}</td>
                <td class="td">{{ $t->cashier?->name }}</td>
                <td class="td text-right">Rp {{ number_format($t->total, 0, ',', '.') }}</td>
                <td class="td"><span class="{{ $t->status === 'selesai' ? 'badge-ok' : 'badge-bad' }}">{{ ucfirst($t->status) }}</span></td>
                <td class="td text-right"><a href="{{ route('transactions.show', $t) }}" class="btn-secondary btn-sm">Detail</a>
                    <a href="{{ route('transactions.receipt', $t) }}" target="_blank" class="btn-secondary btn-sm">Struk</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="td py-10 text-center text-stone-400">Belum ada transaksi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $transactions->links() }}</div>
@endsection
