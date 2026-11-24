@extends('layouts.app')
@section('title', 'Laporan')
@section('heading', 'Laporan')
@section('breadcrumb', 'Laporan / ' . $report['title'])
@section('actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> CSV</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> Excel (.xlsx)</a>
@endsection
@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6" x-data="{ p: @js($periode) }">
    <select name="type" class="input lg:col-span-2">@foreach ($types as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach</select>
    <select name="periode" x-model="p" class="input">@foreach ($presets as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
    <input type="date" name="date_from" value="{{ $dateFrom }}" class="input" x-show="p === 'custom'" x-cloak>
    <input type="date" name="date_to" value="{{ $dateTo }}" class="input" x-show="p === 'custom'" x-cloak>
    @if ($branches->isNotEmpty())
        <select name="branch_id" class="input"><option value="">Semua cabang</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select>
    @endif
    <button class="btn-primary" type="submit">Tampilkan</button>
</form>

<p class="mb-3 text-sm text-stone-500">{{ $report['title'] }} · {{ $type === 'stock' ? 'kondisi saat ini' : $rangeLabel }}. Perhitungan penjualan hanya memakai transaksi <strong>selesai</strong>; transaksi dibatalkan tidak dihitung.</p>

@if ($report['summary'])
<div class="mb-4 grid gap-3 sm:grid-cols-3">
    @foreach ($report['summary'] as $label => $value)
        <div class="card p-4"><div class="text-xs text-stone-500">{{ $label }}</div><div class="text-lg font-bold text-brand-900">{{ $value }}</div></div>
    @endforeach
</div>
@endif

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr>@foreach ($report['headings'] as $i => $h)<th class="th {{ in_array($report['formats'][$i], ['int', 'money']) ? 'text-right' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse (array_slice($report['rows'], 0, $limit) as $row)
            <tr>
                @foreach ($row as $i => $cell)
                    @php $f = $report['formats'][$i]; @endphp
                    <td class="td {{ in_array($f, ['int', 'money']) ? 'text-right' : '' }}">
                        @if ($f === 'money')Rp {{ number_format((float) $cell, 0, ',', '.') }}
                        @elseif ($f === 'int'){{ number_format((int) $cell, 0, ',', '.') }}
                        @else{{ $cell }}@endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report['headings']) }}" class="td py-10 text-center text-stone-400">Tidak ada data pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if (count($report['rows']) > $limit)
    <p class="mt-3 text-sm text-stone-500">Menampilkan {{ $limit }} dari {{ count($report['rows']) }} baris. Gunakan ekspor untuk data lengkap.</p>
@endif
@endsection
