@extends('layouts.app')
@section('title', 'Audit Trail')
@section('heading', 'Audit Trail')
@section('breadcrumb', 'Pengaturan / Audit Trail')
@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4">
    <input name="search" value="{{ request('search') }}" placeholder="Cari deskripsi / ID subjek" class="input lg:col-span-2">
    <select name="user_id" class="input"><option value="">Semua pengguna</option>
        @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') === $u->id)>{{ $u->name }}</option>@endforeach</select>
    <select name="branch_id" class="input"><option value="">Semua cabang</option>
        @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select>
    <select name="log_name" class="input"><option value="">Semua modul</option>
        @foreach ($logNames as $n)<option value="{{ $n }}" @selected(request('log_name') === $n)>{{ $n }}</option>@endforeach</select>
    <select name="aktivitas" class="input"><option value="">Semua jenis aktivitas</option>
        @foreach ($aktivitasList as $n)<option value="{{ $n }}" @selected(request('aktivitas') === $n)>{{ $n }}</option>@endforeach</select>
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
    <button class="btn-secondary sm:col-span-3 lg:col-span-1" type="submit">Terapkan</button>
</form>
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Waktu</th><th class="th">Pengguna</th><th class="th">Cabang</th><th class="th">Modul</th><th class="th">Aktivitas</th><th class="th">Deskripsi</th><th class="th text-right">Detail</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($logs as $l)
            <tr>
                <td class="td">{{ $l->created_at?->format('d/m/Y H:i:s') }}</td>
                <td class="td">{{ $l->user?->name ?? '-' }}</td>
                <td class="td">{{ $l->branch?->nama_cabang ?? '-' }}</td>
                <td class="td"><span class="badge-mute">{{ $l->log_name }}</span></td>
                <td class="td">{{ $l->aktivitas }}</td>
                <td class="td max-w-md truncate" title="{{ $l->description }}">{{ $l->description }}</td>
                <td class="td text-right"><a href="{{ route('audit.show', $l) }}" class="btn-secondary btn-sm">Lihat</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="td py-12 text-center text-stone-400"><x-icon name="inbox" class="mx-auto mb-2 h-8 w-8 text-stone-300" />Belum ada aktivitas tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
