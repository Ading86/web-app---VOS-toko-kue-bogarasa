@extends('layouts.app')
@section('title', 'Detail Audit')
@section('heading', 'Detail Aktivitas ' . $log->id)
@section('breadcrumb', 'Pengaturan / Audit Trail / Detail')
@section('actions')<a href="{{ route('audit.index') }}" class="btn-secondary">Kembali</a>@endsection
@section('content')
<div class="card mb-4 grid gap-2 p-4 text-sm sm:grid-cols-2">
    <div><span class="text-stone-500">Waktu:</span> {{ $log->created_at?->format('d/m/Y H:i:s') }}</div>
    <div><span class="text-stone-500">Pengguna:</span> {{ $log->user?->name ?? '-' }} {{ $log->user ? '(' . $log->user->email . ')' : '' }}</div>
    <div><span class="text-stone-500">Cabang:</span> {{ $log->branch?->nama_cabang ?? '-' }}</div>
    <div><span class="text-stone-500">Modul / aktivitas:</span> {{ $log->log_name }} / {{ $log->aktivitas }}</div>
    <div><span class="text-stone-500">Subjek:</span> {{ $log->subject_type ?? '-' }} {{ $log->subject_id }}</div>
    <div><span class="text-stone-500">IP:</span> {{ $log->ip_address ?? '-' }}</div>
    <div class="sm:col-span-2"><span class="text-stone-500">Deskripsi:</span> {{ $log->description }}</div>
    <div class="sm:col-span-2 break-all"><span class="text-stone-500">User agent:</span> {{ $log->user_agent ?? '-' }}</div>
</div>
@if ($keys->isNotEmpty())
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Field</th><th class="th">Nilai sebelum</th><th class="th">Nilai sesudah</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @foreach ($keys as $k)
            @php
                $b = $before[$k] ?? null; $a = $after[$k] ?? null;
                $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_null($v) ? '—' : (string) $v);
            @endphp
            <tr class="{{ $b !== $a ? 'bg-amber-50' : '' }}">
                <td class="td font-mono text-xs">{{ $k }}</td>
                <td class="td whitespace-normal break-all text-red-700">{{ $fmt($b) }}</td>
                <td class="td whitespace-normal break-all text-emerald-700">{{ $fmt($a) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@else
<p class="text-sm text-stone-500">Aktivitas ini tidak menyimpan perubahan nilai.</p>
@endif
@endsection
