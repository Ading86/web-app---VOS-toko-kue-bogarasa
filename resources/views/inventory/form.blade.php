@extends('layouts.app')
@section('title', 'Ubah Stok')
@section('heading', 'Ubah Stok')
@section('breadcrumb', 'Operasional / Persediaan / Ubah')
@section('content')
<form method="POST" action="{{ route('inventory.store') }}" class="card max-w-2xl space-y-4 p-6" x-data="{ jenis: @js(old('jenis', 'penyesuaian')) }">
    @csrf
    @if ($branches->isNotEmpty())
        <div><label class="label">Cabang</label><select name="branch_id" class="input" required><option value="">-- pilih cabang --</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $preBranch) === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select></div>
    @else
        <p class="text-sm text-stone-500">Stok yang diubah: cabang Anda ({{ auth()->user()->branch?->nama_cabang }}).</p>
    @endif
    <div><label class="label">Produk</label><select name="product_id" class="input" required><option value="">-- pilih produk --</option>
        @foreach ($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $preProduct) === $p->id)>{{ $p->nama_produk }} ({{ $p->kode_produk }})</option>@endforeach</select></div>
    <div><label class="label">Jenis perubahan</label>
        <select name="jenis" x-model="jenis" class="input">
            <option value="stok_masuk" @selected(old('jenis') === 'stok_masuk')>Stok masuk / produksi (khusus Cabang Pusat, masuk ke gudang)</option>
            <option value="stok_keluar" @selected(old('jenis') === 'stok_keluar')>Stok keluar (kurangi: rusak, sampel, dll.)</option>
            <option value="penyesuaian" @selected(old('jenis', 'penyesuaian') === 'penyesuaian')>Koreksi (set stok menjadi nilai tertentu)</option>
            <option value="minimum" @selected(old('jenis') === 'minimum')>Atur stok minimum (batas stok menipis)</option>
        </select>
        <p class="mt-1 text-xs text-stone-500">Cabang selain pusat menerima stok lewat menu <a href="{{ route('distributions.index') }}" class="underline">Distribusi Stok</a>, bukan stok masuk langsung.</p></div>
    <div x-show="jenis === 'stok_keluar' || jenis === 'penyesuaian'" x-cloak><label class="label">Kantong stok yang diubah</label>
        <select name="lokasi" class="input">
            <option value="jual" @selected(old('lokasi', 'jual') === 'jual')>Stok jual (siap dijual di kasir)</option>
            <option value="gudang" @selected(old('lokasi') === 'gudang')>Gudang pusat (hanya Cabang Pusat)</option>
        </select></div>
    <div><label class="label"><span x-show="jenis === 'stok_masuk' || jenis === 'stok_keluar'">Jumlah</span><span x-show="jenis === 'penyesuaian'" x-cloak>Stok menjadi</span><span x-show="jenis === 'minimum'" x-cloak>Stok minimum</span></label>
        <input type="number" name="jumlah" min="0" value="{{ old('jumlah') }}" class="input" required></div>
    <div><label class="label">Keterangan <span class="text-xs text-stone-400" x-show="jenis === 'stok_keluar' || jenis === 'penyesuaian'">(wajib)</span></label>
        <input name="keterangan" value="{{ old('keterangan') }}" class="input" maxlength="255"></div>
    <div class="flex gap-2"><button type="submit" class="btn-primary">Simpan</button><a href="{{ route('inventory.index') }}" class="btn-secondary">Batal</a></div>
</form>
@endsection
