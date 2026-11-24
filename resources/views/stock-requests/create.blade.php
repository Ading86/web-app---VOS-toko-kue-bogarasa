@extends('layouts.app')

@section('title', 'Ajukan Permintaan Stok Baru')
@section('heading', 'Pengajuan Permintaan Stok Kue')
@section('breadcrumb', 'Operasional / Permintaan Stok / Baru')
@section('actions')
    <a href="{{ route('stock-requests.index') }}" class="btn-secondary">Kembali</a>
@endsection

@section('content')
<div class="mb-5 rounded-2xl border border-amber-200/80 bg-amber-50/70 p-4 text-sm text-stone-700 shadow-sm">
    <div class="flex items-start gap-3">
        <div class="rounded-xl bg-amber-100 p-2 text-amber-800 shrink-0">
            <x-icon name="inbox" class="h-5 w-5" />
        </div>
        <div class="leading-relaxed">
            <p>
                Permintaan stok ini akan dikirimkan kepada <strong>Owner</strong> untuk diperiksa dan disetujui. Setelah disetujui, <strong>Petugas Warehouse</strong> akan memproses pengiriman kue dari Gudang Pusat ke cabang Anda.
            </p>
            <p class="mt-1 text-xs text-stone-600">
                Pilih produk kue yang stoknya dibutuhkan dan tentukan jumlah yang diajukan.
            </p>
        </div>
    </div>
</div>

<form method="POST"
      action="{{ route('stock-requests.store') }}"
      class="space-y-5"
      x-data="stockRequestForm()"
      @submit="busy = true">
    @csrf

    {{-- Kartu Info Cabang Pemohon --}}
    <div class="card p-5">
        <h3 class="text-base font-semibold text-stone-800 mb-3 border-b border-stone-200 pb-2">Informasi Pengajuan</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label font-semibold text-stone-700">Cabang Pemohon</label>
                @if ($isOwner)
                    <select name="branch_id" class="input font-semibold text-stone-800">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id_cabang }}" @selected($b->id_cabang === $branch->id_cabang)>
                                {{ $b->nama_cabang }} @if($b->is_pusat) (Pusat) @endif
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text"
                           value="{{ $branch->nama_cabang }} ({{ $branch->kode_cabang }})"
                           class="input bg-stone-100 font-semibold text-stone-700"
                           readonly>
                    <input type="hidden" name="branch_id" value="{{ $branch->id_cabang }}">
                @endif
            </div>

            <div>
                <label class="label font-semibold text-stone-700">Catatan Kebutuhan (Opsional)</label>
                <input name="catatan"
                       value="{{ old('catatan') }}"
                       maxlength="255"
                       placeholder="Misal: Restock akhir pekan, ada pesanan katering, stok menipis, dsb."
                       class="input">
            </div>
        </div>
    </div>

    {{-- Kartu Daftar Produk Yang Diminta --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4 border-b border-stone-200 pb-2">
            <div>
                <h3 class="text-base font-semibold text-stone-800">Daftar Produk Kue</h3>
                <p class="text-xs text-stone-500">Pilih kue dan tentukan jumlah yang ingin diajukan ke Gudang Pusat.</p>
            </div>
            <button type="button"
                    @click="addRow()"
                    class="btn-secondary btn-sm flex items-center gap-1.5">
                <x-icon name="plus" class="h-4 w-4" />
                <span>Tambah Baris Produk</span>
            </button>
        </div>

        @if ($errors->has('items'))
            <div class="mb-4 rounded-xl bg-red-50 p-3 text-xs text-red-700 border border-red-200">
                {{ $errors->first('items') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-left">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200">
                        <th class="th py-3 px-4 w-1/2">Pilih Produk Kue <span class="text-red-500">*</span></th>
                        <th class="th py-3 px-3 text-center">Tipe Kue</th>
                        <th class="th py-3 px-3 text-center">Stok Cabang Saat Ini</th>
                        <th class="th py-3 px-3 text-right w-44">Jumlah Diajukan <span class="text-red-500">*</span></th>
                        <th class="th py-3 px-3 text-center w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 bg-white">
                    <template x-for="(row, index) in rows" :key="row.uid">
                        <tr class="hover:bg-stone-50/50">
                            {{-- Dropdown Produk --}}
                            <td class="td py-3 px-4">
                                <select :name="`items[${index}][product_id]`"
                                        x-model="row.product_id"
                                        @change="onProductChange(index)"
                                        required
                                        class="input text-sm font-semibold text-stone-800">
                                    <option value="">-- Pilih Produk Kue --</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id_produk }}">
                                            {{ $p->nama_produk }} ({{ $p->kode_produk }}) - {{ $p->category?->nama_kategori }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- Tipe Kue --}}
                            <td class="td py-3 px-3 text-center align-middle">
                                <template x-if="row.product">
                                    <div>
                                        <span x-show="row.product.tipe_kue === 'basah'" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                            Kue Basah
                                        </span>
                                        <span x-show="row.product.tipe_kue === 'kering'" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/25">
                                            Kue Kering
                                        </span>
                                        <span x-show="row.product.tipe_kue === 'lainnya'" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-stone-100 text-stone-700">
                                            Lainnya
                                        </span>
                                    </div>
                                </template>
                                <template x-if="!row.product">
                                    <span class="text-stone-400 text-xs">-</span>
                                </template>
                            </td>

                            {{-- Stok Cabang Saat Ini --}}
                            <td class="td py-3 px-3 text-center align-middle">
                                <template x-if="row.product">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                                          :class="row.stock > 0 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'"
                                          x-text="row.stock + ' pcs'">
                                    </span>
                                </template>
                                <template x-if="!row.product">
                                    <span class="text-stone-400 text-xs">-</span>
                                </template>
                            </td>

                            {{-- Input Kuantitas Permintaan --}}
                            <td class="td py-3 px-3 text-right align-middle">
                                <div class="flex items-center justify-end gap-1">
                                    <input type="number"
                                           :name="`items[${index}][qty]`"
                                           x-model.number="row.qty"
                                           min="1"
                                           max="1000000"
                                           required
                                           placeholder="0"
                                           class="input text-right font-bold text-stone-800 w-28">
                                    <span class="text-xs font-semibold text-stone-500">pcs</span>
                                </div>
                            </td>

                            {{-- Tombol Hapus Baris --}}
                            <td class="td py-3 px-3 text-center align-middle">
                                <button type="button"
                                        @click="removeRow(index)"
                                        :disabled="rows.length <= 1"
                                        class="p-1.5 text-stone-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition disabled:opacity-30 disabled:hover:bg-transparent"
                                        title="Hapus baris">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Ringkasan Total --}}
        <div class="mt-4 flex flex-wrap items-center justify-between rounded-xl border border-stone-200 bg-stone-50 p-3.5 text-xs text-stone-600">
            <div class="flex items-center gap-4">
                <span>Total Item: <strong class="text-stone-800" x-text="rows.filter(r => r.product_id).length + ' produk'">0 produk</strong></span>
                <span>Total Kuantitas: <strong class="text-sm font-bold text-[#552918]" x-text="getTotalQty() + ' pcs'">0 pcs</strong></span>
            </div>
            <div class="text-stone-500 italic">
                * Kuantitas yang disetujui dapat disesuaikan oleh Owner berdasarkan ketersediaan stok gudang.
            </div>
        </div>
    </div>

    {{-- Tombol Simpan --}}
    <div class="flex items-center gap-3">
        <button type="submit"
                class="btn-primary flex items-center gap-2"
                :disabled="busy || getTotalQty() < 1">
            <x-icon name="check" class="h-4 w-4" />
            <span x-text="busy ? 'Mengirim Pengajuan...' : 'Kirim Pengajuan Permintaan'">Kirim Pengajuan Permintaan</span>
        </button>
        <a href="{{ route('stock-requests.index') }}" class="btn-secondary">Batal</a>
    </div>
</form>

<script>
function stockRequestForm() {
    const productsMap = {
        @foreach ($products as $p)
            '{{ $p->id_produk }}': {
                id: '{{ $p->id_produk }}',
                nama_produk: '{{ addslashes($p->nama_produk) }}',
                kode_produk: '{{ $p->kode_produk }}',
                tipe_kue: '{{ $p->tipe_kue }}',
                harga: {{ (float) $p->harga }},
            },
        @endforeach
    };

    const branchStocksMap = {
        @foreach ($branchStocks as $pid => $stk)
            '{{ $pid }}': {{ (int) $stk }},
        @endforeach
    };

    return {
        busy: false,
        rows: [
            { uid: 1, product_id: '', product: null, stock: 0, qty: 10 }
        ],
        nextUid: 2,

        addRow() {
            this.rows.push({
                uid: this.nextUid++,
                product_id: '',
                product: null,
                stock: 0,
                qty: 10
            });
        },

        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },

        onProductChange(index) {
            const row = this.rows[index];
            if (row.product_id && productsMap[row.product_id]) {
                row.product = productsMap[row.product_id];
                row.stock = branchStocksMap[row.product_id] || 0;
            } else {
                row.product = null;
                row.stock = 0;
            }
        },

        getTotalQty() {
            return this.rows.reduce((sum, r) => {
                const val = parseInt(r.qty, 10);
                return sum + (!isNaN(val) && val > 0 && r.product_id ? val : 0);
            }, 0);
        }
    };
}
</script>
@endsection
