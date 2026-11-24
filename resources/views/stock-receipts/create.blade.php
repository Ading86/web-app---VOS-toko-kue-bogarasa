@extends('layouts.app')
@section('title', 'Penerimaan Stok Baru')
@section('heading', 'Form Penerimaan Stok Gudang Pusat')
@section('breadcrumb', 'Operasional / Penerimaan Stok / Baru')

@section('actions')
    <a href="{{ route('stock-receipts.index') }}" class="btn-secondary">Kembali</a>
@endsection

@section('content')
<div class="mb-4 rounded-lg bg-brand-50 p-4 text-sm text-stone-700">
    Pencatatan penerimaan stok kue baru di <strong>Gudang Pusat ({{ $pusat->nama_cabang }})</strong>.
    Stok kue basah otomatis memiliki masa layak jual <strong>1 hari</strong> (hanya berlaku hari penerimaan).
    Kue kering mengikuti masa layak jual yang dikonfigurasi pada masing-masing produk.
</div>

<form method="POST"
      action="{{ route('stock-receipts.store') }}"
      x-data="stockReceiptForm()"
      @submit="submitForm($event)"
      class="space-y-6">
    @csrf

    {{-- Kartu Informasi Penerimaan --}}
    <div class="card p-5">
        <h3 class="text-base font-semibold text-stone-800 mb-4 border-b border-stone-200 pb-2">Informasi Penerimaan</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Nomor Penerimaan</label>
                <input type="text"
                       name="nomor_penerimaan"
                       value="{{ old('nomor_penerimaan', $nomorPenerimaan ?? $previewNomor ?? '') }}"
                       class="input bg-stone-100 font-mono font-semibold text-brand-700 cursor-not-allowed"
                       readonly>
                <p class="mt-1 text-xs text-stone-400">Dibuat otomatis dan unik</p>
            </div>

            <div>
                <label class="label">Tanggal Penerimaan <span class="text-red-500">*</span></label>
                <input type="date"
                       name="tanggal_penerimaan"
                       x-model="tanggalPenerimaan"
                       @change="recalculateExpirations()"
                       required
                       class="input @error('tanggal_penerimaan') border-red-500 @enderror">
                @error('tanggal_penerimaan')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Petugas Penerima</label>
                <input type="text"
                       value="{{ auth()->user()->name }}"
                       class="input bg-stone-100 text-stone-600 cursor-not-allowed"
                       readonly>
                <p class="mt-1 text-xs text-stone-400">Akun yang sedang aktif</p>
            </div>

            <div>
                <label class="label">Catatan Penerimaan (Opsional)</label>
                <input type="text"
                       name="catatan"
                       value="{{ old('catatan') }}"
                       maxlength="255"
                       placeholder="Misal: Penerimaan batch pagi produksi Dapur A"
                       class="input">
            </div>
        </div>
    </div>

    {{-- Kartu Detail Item Produk --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4 border-b border-stone-200 pb-2">
            <div>
                <h3 class="text-base font-semibold text-stone-800">Daftar Produk Masuk</h3>
                <p class="text-xs text-stone-500">Tambahkan satu atau beberapa produk dalam satu transaksi penerimaan.</p>
            </div>
            <button type="button"
                    @click="addRow()"
                    class="btn-secondary btn-sm flex items-center gap-1.5">
                <x-icon name="plus" class="h-4 w-4" />
                <span>Tambah Baris Produk</span>
            </button>
        </div>

        @if ($errors->has('items'))
            <div class="mb-3 rounded-md bg-red-50 p-2.5 text-xs text-red-700">
                {{ $errors->first('items') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200">
                <thead>
                    <tr>
                        <th class="th w-1/3">Produk</th>
                        <th class="th">Kategori / Tipe</th>
                        <th class="th text-right">Stok Gudang Saat Ini</th>
                        <th class="th text-center">Masa Layak Jual</th>
                        <th class="th text-center">Tgl Kedaluwarsa</th>
                        <th class="th text-right w-36">Jumlah Masuk <span class="text-red-500">*</span></th>
                        <th class="th text-center w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <template x-for="(row, index) in rows" :key="row.uid">
                        <tr>
                            {{-- Dropdown Produk --}}
                            <td class="td">
                                <select :name="`items[${index}][product_id]`"
                                        x-model="row.product_id"
                                        @change="onProductChange(index)"
                                        required
                                        class="input text-sm">
                                    <option value="">-- Pilih Produk --</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id }}">
                                            {{ $p->nama_produk }} ({{ $p->kode_produk }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- Kategori & Tipe --}}
                            <td class="td">
                                <template x-if="row.product">
                                    <div>
                                        <div class="text-xs font-semibold text-stone-700" x-text="row.product.category_name"></div>
                                        <div class="mt-0.5">
                                            <span x-show="row.product.tipe_kue === 'basah'"
                                                  class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800">
                                                Kue Basah
                                            </span>
                                            <span x-show="row.product.tipe_kue === 'kering'"
                                                  class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                                Kue Kering
                                            </span>
                                            <span x-show="row.product.tipe_kue === 'lainnya'"
                                                  class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-stone-100 text-stone-700">
                                                Lainnya
                                            </span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!row.product">
                                    <span class="text-xs text-stone-400">-</span>
                                </template>
                            </td>

                            {{-- Stok Gudang Pusat Saat Ini --}}
                            <td class="td text-right font-medium text-stone-600">
                                <span x-text="row.product ? row.product.stok_gudang.toLocaleString('id-ID') + ' pcs' : '-'"></span>
                            </td>

                            {{-- Masa Layak Jual --}}
                            <td class="td text-center text-xs">
                                <template x-if="row.product">
                                    <div>
                                        <span class="font-semibold text-stone-800" x-text="row.masa_layak + ' Hari'"></span>
                                        <div x-show="row.product.tipe_kue === 'basah'" class="text-[10px] text-blue-600 font-medium">(Otomatis 1 Hari)</div>
                                        <div x-show="row.product.tipe_kue === 'kering'" class="text-[10px] text-stone-500">(Setting Produk)</div>
                                    </div>
                                </template>
                                <template x-if="!row.product">
                                    <span class="text-stone-400">-</span>
                                </template>
                            </td>

                            {{-- Tanggal Kedaluwarsa --}}
                            <td class="td text-center text-xs">
                                <template x-if="row.product">
                                    <span class="font-semibold text-brand-700" x-text="formatDateId(row.exp_date)"></span>
                                </template>
                                <template x-if="!row.product">
                                    <span class="text-stone-400">-</span>
                                </template>
                            </td>

                            {{-- Input Jumlah Masuk --}}
                            <td class="td text-right">
                                <input type="number"
                                       :name="`items[${index}][jumlah]`"
                                       x-model.number="row.jumlah"
                                       min="1"
                                       max="1000000"
                                       required
                                       placeholder="0"
                                       class="input text-right font-semibold text-stone-800">
                            </td>

                            {{-- Tombol Hapus Baris --}}
                            <td class="td text-center">
                                <button type="button"
                                        @click="removeRow(index)"
                                        :disabled="rows.length <= 1"
                                        title="Hapus baris"
                                        class="rounded p-1 text-red-500 hover:bg-red-50 disabled:opacity-30 disabled:cursor-not-allowed">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Ringkasan Total & Submit --}}
        <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-t border-stone-200 pt-4">
            <div class="flex items-center gap-6 text-sm">
                <div>
                    <span class="text-stone-500">Total Jenis Produk:</span>
                    <strong class="ml-1 text-stone-800" x-text="validRowsCount() + ' jenis'"></strong>
                </div>
                <div>
                    <span class="text-stone-500">Total Kuantitas Masuk:</span>
                    <strong class="ml-1 text-base text-brand-700" x-text="totalQuantity().toLocaleString('id-ID') + ' pcs'"></strong>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('stock-receipts.index') }}" class="btn-secondary">Batal</a>
                <button type="submit"
                        class="btn-primary"
                        :disabled="busy || validRowsCount() === 0">
                    <span x-show="!busy">Simpan Penerimaan Stok</span>
                    <span x-show="busy">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function stockReceiptForm() {
    const productsMap = {
        @foreach ($products as $p)
            '{{ $p->id }}': {
                id: '{{ $p->id }}',
                nama: '{{ addslashes($p->nama_produk) }}',
                kode: '{{ $p->kode_produk }}',
                category_name: '{{ addslashes($p->category?->nama_kategori ?? "-") }}',
                tipe_kue: '{{ $p->tipe_kue }}',
                masa_layak: {{ $p->tipe_kue === 'basah' ? 1 : (int) ($p->masa_layak_jual ?: 5) }},
                stok_gudang: {{ (int) ($gudangStocks[$p->id] ?? 0) }}
            },
        @endforeach
    };

    let nextUid = 1;

    return {
        busy: false,
        tanggalPenerimaan: '{{ old('tanggal_penerimaan', $defaultTanggal ?? $today ?? date('Y-m-d')) }}',
        rows: [
            { uid: nextUid++, product_id: '', product: null, jumlah: '', masa_layak: 1, exp_date: '' }
        ],

        init() {
            this.recalculateExpirations();
        },

        addRow() {
            this.rows.push({
                uid: nextUid++,
                product_id: '',
                product: null,
                jumlah: '',
                masa_layak: 1,
                exp_date: ''
            });
        },

        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },

        onProductChange(index) {
            const row = this.rows[index];
            const p = productsMap[row.product_id];
            if (p) {
                row.product = p;
                row.masa_layak = p.masa_layak;
                row.exp_date = this.computeExpDate(p.masa_layak);
            } else {
                row.product = null;
                row.masa_layak = 1;
                row.exp_date = '';
            }
        },

        recalculateExpirations() {
            this.rows.forEach(row => {
                if (row.product) {
                    row.exp_date = this.computeExpDate(row.masa_layak);
                }
            });
        },

        computeExpDate(days) {
            if (!this.tanggalPenerimaan) return '';
            const d = new Date(this.tanggalPenerimaan + 'T00:00:00');
            if (isNaN(d.getTime())) return '';
            // Hari ke-1 adalah hari itu sendiri, jadi add (days - 1)
            const add = Math.max(0, parseInt(days || 1) - 1);
            d.setDate(d.getDate() + add);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        formatDateId(dateStr) {
            if (!dateStr) return '-';
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            return dateStr;
        },

        validRowsCount() {
            return this.rows.filter(r => r.product_id && parseInt(r.jumlah) > 0).length;
        },

        totalQuantity() {
            return this.rows.reduce((sum, r) => {
                const j = parseInt(r.jumlah);
                return sum + (!isNaN(j) && j > 0 ? j : 0);
            }, 0);
        },

        submitForm(e) {
            if (this.validRowsCount() === 0) {
                e.preventDefault();
                alert('Silakan pilih minimal 1 produk dan masukkan jumlah yang valid.');
                return;
            }
            this.busy = true;
        }
    };
}
</script>
@endsection
