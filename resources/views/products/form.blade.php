@extends('layouts.app')
@php($editing = $product->exists)
@section('title', $editing ? 'Ubah Produk' : 'Tambah Produk')
@section('heading', $editing ? 'Ubah Produk' : 'Tambah Produk')
@section('breadcrumb', 'Master Data / Produk / ' . ($editing ? 'Ubah' : 'Tambah'))
@section('content')
<form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data"
      class="card max-w-3xl space-y-4 p-6"
      x-data="{
          tipe: @js(old('tipe_kue', $product->tipe_kue ?? 'standard')),
          masaLayak: @js((int) old('masa_layak_jual', $product->masa_layak_jual ?: 5)),
          get showMasaLayak() { return this.tipe === 'kering'; },
          get showBasahInfo() { return this.tipe === 'basah'; },
          onTipeChange() {
              if (this.tipe === 'kering' && (!this.masaLayak || this.masaLayak <= 1)) {
                  this.masaLayak = 5;
              }
              if (this.tipe === 'basah') {
                  this.masaLayak = 1;
              }
          }
      }">
    @csrf @if ($editing) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Nama produk</label><input name="nama_produk" value="{{ old('nama_produk', $product->nama_produk) }}" class="input" required></div>
        <div><label class="label">Kode produk</label><input name="kode_produk" value="{{ old('kode_produk', $product->kode_produk) }}" class="input" placeholder="Kosongkan untuk otomatis">
            </div>
        <div><label class="label">Kategori</label><select name="category_id" class="input" required><option value="">-- pilih --</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) === $c->id)>{{ $c->nama_kategori }}</option>@endforeach</select></div>
        <div><label class="label">Harga (Rp)</label><input type="number" name="harga" min="0" step="1" value="{{ old('harga', $product->harga !== null ? (int) $product->harga : '') }}" class="input" required></div>
        <div><label class="label">Status</label><select name="status" class="input">
            @foreach (['aktif', 'nonaktif'] as $s)<option value="{{ $s }}" @selected(old('status', $product->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div><label class="label">Gambar (jpg/png/webp, maks 2 MB)</label><input type="file" name="gambar" accept="image/png,image/jpeg,image/webp" class="input"></div>
    </div>

    {{-- ── Tipe kue & masa layak jual ─────────────────────────────────── --}}
    <div class="rounded-lg border border-stone-200 bg-stone-50 p-4 space-y-3">
        <div class="font-medium text-stone-700 text-sm">Aturan Stok Kue</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Tipe kue</label>
                <select name="tipe_kue"
                        x-model="tipe"
                        @change="onTipeChange()"
                        class="input">
                    @foreach (\App\Models\Product::TIPE_OPTIONS as $k => $v)
                        <option value="{{ $k }}" @selected(old('tipe_kue', $product->tipe_kue ?? 'standard') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-stone-500">
                    <span x-show="tipe === 'basah'">Kue basah hanya berlaku 1 hari (hanya untuk tanggal penerimaan).</span>
                    <span x-show="tipe === 'kering'">Kue kering dapat disimpan dan dijual selama beberapa hari.</span>
                    <span x-show="tipe === 'standard'">Stok umum tanpa aturan batas hari kedaluwarsa.</span>
                </p>
            </div>

            <div>
                {{-- KUE KERING: input angka, bisa diatur --}}
                <template x-if="tipe === 'kering'">
                    <div>
                        <label class="label">Masa layak jual (hari) <span class="text-red-500">*</span></label>
                        <input type="number"
                               name="masa_layak_jual"
                               x-model.number="masaLayak"
                               min="1"
                               max="365"
                               required
                               class="input"
                               placeholder="5">
                        <p class="mt-1 text-xs text-stone-500">
                            Jumlah hari kue kering layak dijual sejak tanggal penerimaan (default: 5 hari).
                        </p>
                    </div>
                </template>

                {{-- KUE BASAH: readonly, otomatis 1 hari --}}
                <template x-if="tipe === 'basah'">
                    <div>
                        <label class="label">Masa layak jual</label>
                        <input type="text"
                               value="1 Hari (Otomatis — aturan tetap toko)"
                               class="input bg-stone-100 text-stone-500 cursor-not-allowed"
                               readonly
                               tabindex="-1">
                        <input type="hidden" name="masa_layak_jual" value="1">
                        <p class="mt-1 text-xs text-stone-500">
                            Kue basah selalu berlaku 1 hari — tidak bisa diubah.
                        </p>
                    </div>
                </template>

                {{-- STANDARD: tidak ada masa layak --}}
                <template x-if="tipe === 'standard'">
                    <div>
                        <label class="label">Masa layak jual</label>
                        <input type="text"
                               value="Tidak ada (stok tanpa batas waktu)"
                               class="input bg-stone-100 text-stone-500 cursor-not-allowed"
                               readonly
                               tabindex="-1">
                        <input type="hidden" name="masa_layak_jual" value="0">
                        <p class="mt-1 text-xs text-stone-500">
                            Produk standar tidak memiliki aturan kedaluwarsa.
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    @if ($editing && $product->gambar)
        <div class="flex items-center gap-4">
            <img src="{{ asset('storage/' . $product->gambar) }}" alt="" class="h-20 w-20 rounded-lg object-cover">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="hapus_gambar" value="1" class="rounded border-stone-300"> Hapus gambar</label>
        </div>
    @endif

    <div>
        <label class="label">Deskripsi (opsional)</label>
        <textarea name="deskripsi" rows="3" class="input">{{ old('deskripsi', $product->deskripsi) }}</textarea>
    </div>

    <p class="text-xs text-brand-600">Produk berlaku untuk semua cabang; stok dikelola terpisah per cabang di menu Persediaan.</p>

    <div class="flex gap-2 pt-2">
        <button type="submit" class="btn-primary">Simpan</button>
        <a href="{{ route('products.index') }}" class="btn-secondary">Batal</a>
    </div>
</form>
@endsection
