@extends('layouts.app')
@section('title', 'Role & Permission')
@section('heading', 'Role & Hak Akses')
@section('breadcrumb', 'Pengaturan / Role & Permission')
@section('actions')<a href="{{ route('roles.create') }}" class="btn-primary">Role Baru</a>@endsection
@section('content')
<div class="card mb-6 overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Role</th><th class="th">Pengguna</th><th class="th">Permission</th><th class="th text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @foreach ($roles as $r)
            <tr>
                <td class="td font-medium">{{ $r->name }}</td>
                <td class="td">{{ $r->users_count }}</td>
                <td class="td">{{ $r->permissions->count() }} / {{ $permissions->count() }}</td>
                <td class="td text-right">
                    @if ($r->name === 'Owner')
                        <span class="badge-mute">Terkunci (akses penuh)</span>
                    @else
                        <a href="{{ route('roles.edit', $r) }}" class="btn-secondary btn-sm">Atur hak akses</a>
                        @if (! in_array($r->name, ['Owner', 'Kasir']) && $r->users_count === 0)
                            <form method="POST" action="{{ route('roles.destroy', $r) }}" class="inline" data-confirm="Hapus role {{ $r->name }}?">@csrf @method('DELETE')
                                <button class="btn-danger btn-sm" type="submit">Hapus</button></form>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<h2 class="mb-2 font-semibold text-brand-900">Matriks hak akses</h2>
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-stone-200">
        <thead><tr><th class="th">Permission</th>@foreach ($roles as $r)<th class="th text-center">{{ $r->name }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-stone-100">
        @foreach ($permissions as $p)
            <tr>
                <td class="td"><span class="font-medium">{{ \App\Support\PermissionLabels::group(\Illuminate\Support\Str::before($p->name, '.')) }}</span>
                    · {{ \App\Support\PermissionLabels::action($p->name) }} <span class="text-xs text-stone-400">{{ $p->name }}</span></td>
                @foreach ($roles as $r)
                    <td class="td text-center">@if ($r->permissions->contains('id', $p->id))<x-icon name="check" class="mx-auto h-4 w-4 text-emerald-600" />@else<span class="text-stone-300">–</span>@endif</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
