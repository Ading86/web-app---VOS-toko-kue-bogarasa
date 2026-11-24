@extends('layouts.app')
@php($editing = $role->exists)
@php($system = $editing && in_array($role->name, ['Owner', 'Kasir']))
@section('title', $editing ? 'Atur Hak Akses' : 'Role Baru')
@section('heading', $editing ? 'Atur Hak Akses: ' . $role->name : 'Role Baru')
@section('breadcrumb', 'Pengaturan / Role & Permission')

@push('head')
<style>
/* ── Toggle Switch (Hanya 2 Status Warna: Abu-abu & Coklat) ── */
.perm-switch {
    appearance: none;
    -webkit-appearance: none;
    position: relative;
    display: inline-block;
    flex-shrink: 0;
    cursor: pointer;
    border-radius: 9999px;
    transition: background-color .2s ease, border-color .2s ease;
    background-color: #d6cfc6; /* OFF: Abu-abu */
    border: 2px solid #c5bbb0;
}
/* Individual switch: 36×20 */
.perm-switch.perm-switch-sm {
    width: 36px;
    height: 20px;
}
/* Switch-all (Grup & Global): 44×24 */
.perm-switch.perm-switch-lg {
    width: 44px;
    height: 24px;
}
.perm-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    border-radius: 9999px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,.25);
    transition: transform .2s ease;
}
.perm-switch.perm-switch-sm::after { width: 12px; height: 12px; }
.perm-switch.perm-switch-lg::after { width: 16px; height: 16px; }

/* ON: Coklat brand #4A2618 */
.perm-switch:checked {
    background-color: #4A2618;
    border-color: #3a1e12;
}
.perm-switch.perm-switch-sm:checked::after { transform: translateX(16px); }
.perm-switch.perm-switch-lg:checked::after { transform: translateX(20px); }

.perm-switch:disabled { opacity: .45; cursor: not-allowed; }
.perm-switch:focus-visible { outline: 2px solid #4A2618; outline-offset: 2px; }
</style>
@endpush

@section('content')
<form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}" class="space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif

    {{-- Baris Atas: Nama Role --}}
    <div class="card max-w-md p-4">
        <label class="label">Nama role</label>
        <input name="name" value="{{ old('name', $role->name) }}" class="input" required @readonly($system)>
        @if ($system)<p class="mt-1 text-xs text-stone-500">Nama role sistem tidak dapat diubah.</p>@endif
    </div>

    {{-- Switch All Global untuk Semua Kotak --}}
    <div class="card p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white border border-stone-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-brand-900 border border-amber-200/70">
                <x-icon name="shield" class="h-5 w-5" />
            </div>
            <div>
                <h3 class="font-semibold text-brand-900 text-sm">Hak Akses Modul & Fitur</h3>
                <p class="text-xs text-stone-500">Kelola izin per modul atau aktifkan/nonaktifkan seluruh hak akses sekaligus</p>
            </div>
        </div>
        <label class="inline-flex items-center gap-3 cursor-pointer select-none bg-stone-50 hover:bg-stone-100/90 px-3.5 py-2 rounded-xl border border-stone-200 transition-colors" title="Aktifkan atau nonaktifkan semua hak akses">
            <span class="text-xs font-semibold text-stone-700">Pilih Semua Hak Akses</span>
            <input type="checkbox" id="global-toggle-all" class="perm-switch perm-switch-lg">
        </label>
    </div>

    {{-- Grid Permission per Grup --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($permissions->groupBy(fn ($p) => \Illuminate\Support\Str::before($p->name, '.')) as $group => $items)
            <div class="card p-4">

                {{-- Header Kotak: nama grup + switch-all per kotak --}}
                <div class="flex items-center justify-between mb-3 border-b border-stone-100 pb-2.5">
                    <span class="font-semibold text-brand-900 text-sm">
                        {{ \App\Support\PermissionLabels::group($group) }}
                    </span>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none" title="Pilih semua hak akses di modul ini">
                        <span class="text-xs font-medium text-stone-500">Semua</span>
                        <input type="checkbox"
                               class="perm-switch perm-switch-lg toggle-all"
                               data-group="{{ $group }}">
                    </label>
                </div>

                {{-- Daftar permission dalam kotak --}}
                <div class="space-y-2.5 group-permissions" data-group="{{ $group }}">
                    @foreach ($items as $p)
                        <label class="flex items-center justify-between gap-3 cursor-pointer group/item">
                            <span class="flex flex-col min-w-0">
                                <span class="text-sm font-medium text-stone-800 group-hover/item:text-brand-900 transition-colors">
                                    {{ \App\Support\PermissionLabels::action($p->name) }}
                                </span>
                                <span class="text-[11px] text-stone-400 font-mono truncate">{{ $p->name }}</span>
                            </span>
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $p->name }}"
                                   class="perm-switch perm-switch-sm group-toggle"
                                   data-group="{{ $group }}"
                                   @checked(in_array($p->name, old('permissions', $selected)))>
                        </label>
                    @endforeach
                </div>

            </div>
        @endforeach
    </div>

    <p class="text-sm text-stone-500">Perubahan berlaku pada permintaan berikutnya, tanpa perlu logout.</p>

    <div class="flex gap-2">
        <button type="submit" class="btn-primary">Simpan</button>
        <a href="{{ route('roles.index') }}" class="btn-secondary">Batal</a>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const globalToggle = document.getElementById('global-toggle-all');
    const groupMasters = Array.from(document.querySelectorAll('.toggle-all'));
    const allChildToggles = Array.from(document.querySelectorAll('.group-toggle'));

    // Sinkronisasi status switch per grup dan global
    const syncAll = () => {
        // Update tiap switch-all grup
        groupMasters.forEach(master => {
            const group = master.dataset.group;
            const children = allChildToggles.filter(c => c.dataset.group === group);
            const allChecked = children.length > 0 && children.every(c => c.checked);
            master.checked = allChecked;
            master.indeterminate = false; // Tanpa status sebagian (selalu 2 warna saja)
        });

        // Update switch-all global
        const isGlobalChecked = allChildToggles.length > 0 && allChildToggles.every(c => c.checked);
        if (globalToggle) {
            globalToggle.checked = isGlobalChecked;
            globalToggle.indeterminate = false; // Tanpa status sebagian
        }
    };

    // Event: Switch-all Global ditekan
    if (globalToggle) {
        globalToggle.addEventListener('change', () => {
            const state = globalToggle.checked;
            allChildToggles.forEach(c => { c.checked = state; });
            groupMasters.forEach(m => { m.checked = state; m.indeterminate = false; });
        });
    }

    // Event: Switch-all per Grup ditekan
    groupMasters.forEach(master => {
        master.addEventListener('change', () => {
            const group = master.dataset.group;
            const children = allChildToggles.filter(c => c.dataset.group === group);
            children.forEach(c => { c.checked = master.checked; });
            syncAll();
        });
    });

    // Event: Switch permission individual ditekan
    allChildToggles.forEach(child => {
        child.addEventListener('change', syncAll);
    });

    // Inisialisasi awal saat halaman dimuat
    syncAll();
});
</script>
@endpush

@endsection
