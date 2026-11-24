@extends('layouts.app')
@section('title', 'Pengguna')
@section('heading', 'Manajemen Pengguna')
@section('breadcrumb', 'Pengaturan / Pengguna')
@section('actions')
    @can('users.manage')<a href="{{ route('users.create') }}" class="btn-primary">Tambah Pengguna</a>@endcan
@endsection
@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
    <input name="search" value="{{ request('search') }}" placeholder="Cari nama / email" class="input">
    <select name="role" class="input"><option value="">Semua role</option>
        @foreach ($roles as $r)<option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ $r->name }}</option>@endforeach</select>
    <select name="branch_id" class="input"><option value="">Semua cabang</option>
        @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select>
    <button class="btn-secondary" type="submit">Terapkan</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Nama</th><th class="th">Email</th><th class="th">Role</th><th class="th">Cabang</th><th class="th">Status</th><th class="th text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($users as $u)
            <tr>
                <td class="td font-medium">{{ $u->name }}</td>
                <td class="td">{{ $u->email }}</td>
                <td class="td">@foreach ($u->roles as $r)<span class="badge-mute">{{ $r->name }}</span>@endforeach</td>
                <td class="td">{{ $u->branch?->nama_cabang ?? 'Semua cabang' }}</td>
                <td class="td">@if ($u->is_active)<span class="badge-ok">Aktif</span>@else<span class="badge-bad">Nonaktif</span>@endif</td>
                <td class="td text-right">
                    @can('users.manage')
                        <a href="{{ route('users.edit', $u) }}" class="btn-secondary btn-sm">Ubah</a>
                        @if (! $u->is(auth()->user()))
                            <form method="POST" action="{{ route('users.toggle', $u) }}" class="inline"
                                  data-confirm="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $u->email }}?">@csrf
                                <button class="btn-secondary btn-sm" type="submit">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="td py-10 text-center text-stone-400">Belum ada pengguna.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
