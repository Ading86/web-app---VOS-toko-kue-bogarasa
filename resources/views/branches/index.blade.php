@extends('layouts.app')
@section('title', 'Cabang')
@section('heading', 'Manajemen Cabang')
@section('breadcrumb', 'Master Data / Cabang')
@section('actions')@can('branches.manage')<a href="{{ route('branches.create') }}" class="btn-primary">Tambah Cabang</a>@endcan @endsection
@section('content')
<form method="GET" class="card mb-4 flex gap-3 p-4">
    <input name="search" value="{{ request('search') }}" placeholder="Cari kode / nama cabang" class="input max-w-sm">
    <button class="btn-secondary" type="submit">Cari</button>
</form>
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Kode</th><th class="th">Nama</th><th class="th">Alamat</th><th class="th">Telepon</th><th class="th">Akun</th><th class="th">Status</th><th class="th text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse ($branches as $b)
            <tr>
                <td class="td font-mono text-xs">{{ $b->kode_cabang }}</td>
                <td class="td font-medium">{{ $b->nama_cabang }} @if ($b->is_pusat)<span class="badge-warn ml-1">Pusat</span>@endif</td>
                <td class="td max-w-xs truncate">{{ $b->alamat ?: '-' }}</td>
                <td class="td">{{ $b->nomor_telepon ?: '-' }}</td>
                <td class="td">{{ $b->users_count }}</td>
                <td class="td"><span class="{{ $b->status === 'aktif' ? 'badge-ok' : 'badge-bad' }}">{{ ucfirst($b->status) }}</span></td>
                <td class="td text-right">
                    @can('branches.manage')
                        <a href="{{ route('branches.edit', $b) }}" class="btn-secondary btn-sm">Ubah</a>
                        @unless ($b->is_pusat && $b->status === 'aktif')
                        <form method="POST" action="{{ route('branches.toggle', $b) }}" class="inline"
                              data-confirm="{{ $b->status === 'aktif' ? 'Nonaktifkan cabang ini? Kasir cabang ini tidak dapat login.' : 'Aktifkan kembali cabang ini?' }}">@csrf
                            <button class="btn-secondary btn-sm" type="submit">{{ $b->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                        @endunless
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="td py-10 text-center text-stone-400">Belum ada cabang.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $branches->links() }}</div>
@endsection
