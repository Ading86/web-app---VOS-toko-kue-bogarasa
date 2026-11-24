@extends('layouts.app')
@section('title', 'Catat Stok Batch')
@section('heading', 'Catat Stok Masuk Batch')
@section('breadcrumb', 'Operasional / Stok Batch / Tambah')
@section('content')
<div class="mb-4 rounded-lg bg-brand-50 p-4 text-sm text-stone-700">
    <strong>Catatan:</strong> Form ini untuk mencatat stok masuk kue basah atau kue kering.
    Sistem akan otomatis menghitung tanggal kedaluwarsa berdasarkan masa layak jual produk.
    Kue basah dengan tanggal produksi <em>hari ini</em> tidak akan tersedia untuk dijual esok hari.
</div>
<form method="POST" action="{{ route('stock-batches.store') }}"
      class="card max-w-2xl space-y-4 p-6"
      x-data="{
          produk: @js(old('product_id')),
          products: @js($products),
          get selectedProduct() { return this.products.find(p => p.id === this.produk) },
          get masaLayak() { return this.selectedProduct?.masa_layak_jual ?? '—' },
          get tipe() { return this.selectedProduct?.tipe_kue ?? '' },
      }">
    @csrf

    @if ($branches->isNotEmpty())
        <div>
            <label class="label">Cabang</label>
            <select name="branch_id" class="input" required>
                <option value="">-- pilih cabang --</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected(old('branch_id', $preBranch) === $b->id)>{{ $b->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
    @else
        <p class="text-sm text-stone-500">Stok dicatat untuk: <strong>{{ auth()->user()->branch?->nama_cabang }}</strong></p>
    @endif

    <div>
        <label class="label">Produk (kue basah / kering)</label>
        <select name="product_id" x-model="produk" class="input" required>
            <option value="">-- pilih produk --</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}" @selected(old('product_id', $preProduct) === $p->id)>
                    {{ $p->nama_produk }} ({{ $p->tipe_kue === 'basah' ? 'Basah' : 'Kering' }})
                </option>
            @endforeach
        </select>
    </div>

    <div x-show="produk" x-cloak>
        <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm">
            <span class="text-stone-500">Tipe:</span>
            <strong x-text="tipe === 'basah' ? 'Kue Basah' : 'Kue Kering'"></strong>
            &nbsp;·&nbsp;
            <span class="text-stone-500">Masa layak jual:</span>
            <strong x-text="masaLayak + ' hari'"></strong>
        </div>
    </div>

    <div>
        <label class="label">Tanggal Produksi / Penerimaan</label>
        <input type="date" name="tanggal_produksi" max="{{ $today }}"
               value="{{ old('tanggal_produksi', $today) }}"
               class="input" required>
        <p class="mt-1 text-xs text-stone-500">
            Tidak boleh di masa depan. Tanggal kedaluwarsa dihitung otomatis.
        </p>
    </div>

    <div>
        <label class="label">Jumlah (pcs)</label>
        <input type="number" name="jumlah" min="1" max="1000000"
               value="{{ old('jumlah') }}"
               class="input" required>
    </div>

    <div>
        <label class="label">Keterangan (opsional)</label>
        <input type="text" name="keterangan" maxlength="150"
               value="{{ old('keterangan') }}"
               class="input"
               placeholder="Mis: Produksi pagi, Penerimaan dari pemasok, dsb.">
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn-primary">Simpan Batch</button>
        <a href="{{ route('stock-batches.index') }}" class="btn-secondary">Batal</a>
    </div>
</form>
@endsection
