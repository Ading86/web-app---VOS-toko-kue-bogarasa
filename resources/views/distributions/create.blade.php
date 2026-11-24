@extends('layouts.app')
@section('title', 'Distribusi Baru')
@section('heading', 'Distribusi Stok Baru')
@section('breadcrumb', 'Operasional / Distribusi Stok / Baru')
@section('actions')
    <a href="{{ route('distributions.index') }}" class="btn-secondary">Kembali</a>
@endsection

@section('content')
<div class="mb-5 rounded-2xl border border-amber-200/80 bg-amber-50/70 p-4 text-sm text-stone-700 shadow-sm">
    <div class="flex items-start gap-3">
        <div class="rounded-xl bg-amber-100 p-2 text-amber-800 shrink-0">
            <x-icon name="truck" class="h-5 w-5" />
        </div>
        <div class="leading-relaxed">
            <p>
                Sumber stok dari <strong>Gudang {{ $pusat->nama_cabang }}</strong>. Masukkan jumlah kue yang akan didistribusikan per produk ke setiap cabang tujuan.
            </p>
            <p class="mt-1 text-xs text-stone-600">
                Kolom <strong class="text-amber-900">{{ $pusat->nama_cabang }} (Alokasi Sendiri)</strong> dialokasikan langsung untuk stok etalase penjualan Cabang Pusat. Stok akan berstatus <em>dalam perjalanan</em> sampai masing-masing cabang mengonfirmasi penerimaan. Kosongkan atau isi 0 jika tidak ada pengiriman.
            </p>
        </div>
    </div>
</div>

<form method="POST"
      action="{{ route('distributions.store') }}"
      class="space-y-5"
      x-data="distributionForm()"
      @submit="busy = true">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

    {{-- Tabel Matriks Distribusi --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-left border-collapse">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200">
                        <th class="th min-w-[220px] py-3.5 px-4 font-semibold text-stone-700">Produk</th>
                        <th class="th min-w-[130px] py-3.5 px-4 text-center font-semibold text-stone-700">
                            Stok Gudang
                            <span class="block text-[10px] font-normal text-stone-500 uppercase tracking-normal">Tersedia</span>
                        </th>
                        @foreach ($branches as $b)
                            <th class="th min-w-[140px] py-3.5 px-3 text-center {{ $b->is_pusat ? 'bg-amber-100/40 border-x border-amber-200/60' : '' }}">
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold {{ $b->is_pusat ? 'text-amber-900' : 'text-stone-700' }}">
                                        {{ $b->nama_cabang }}
                                    </span>
                                    @if ($b->is_pusat)
                                        <span class="inline-flex items-center px-2 py-0.5 mt-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300/80">
                                            Alokasi Sendiri
                                        </span>
                                    @else
                                        <span class="text-[10px] font-mono font-normal text-stone-400 mt-0.5">
                                            {{ $b->kode_cabang }}
                                        </span>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                        <th class="th min-w-[110px] py-3.5 px-3 text-center font-semibold text-stone-700 bg-stone-100/50">
                            Total Kirim
                            <span class="block text-[10px] font-normal text-stone-500 uppercase tracking-normal">Semua Cabang</span>
                        </th>
                        <th class="th min-w-[110px] py-3.5 px-3 text-center font-semibold text-stone-700 bg-stone-100/50">
                            Sisa Gudang
                            <span class="block text-[10px] font-normal text-stone-500 uppercase tracking-normal">Setelah Kirim</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 bg-white">
                @forelse ($products as $p)
                    @php
                        $stkGudang = (int) ($gudang[$p->id] ?? 0);
                    @endphp
                    <tr class="hover:bg-amber-50/20 transition-colors">
                        {{-- Nama & Info Produk --}}
                        <td class="td py-3 px-4 align-middle">
                            <div class="font-semibold text-stone-900">{{ $p->nama_produk }}</div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="font-mono text-xs text-stone-400">{{ $p->kode_produk }}</span>
                                @if ($p->tipe_kue === 'basah')
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                        Kue Basah
                                    </span>
                                @elseif ($p->tipe_kue === 'kering')
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/25">
                                        Kue Kering
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- Stok Gudang Pusat --}}
                        <td class="td py-3 px-4 text-center align-middle">
                            <span class="inline-flex items-center justify-center min-w-[3.5rem] px-2.5 py-1 rounded-full text-xs font-bold {{ $stkGudang > 0 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-stone-100 text-stone-500' }}">
                                {{ number_format($stkGudang, 0, ',', '.') }} pcs
                            </span>
                        </td>

                        {{-- Inputan Jumlah Tiap Cabang --}}
                        @foreach ($branches as $b)
                            <td class="td py-2.5 px-3 text-center align-middle {{ $b->is_pusat ? 'bg-amber-50/20 border-x border-amber-200/40' : '' }}">
                                <div class="flex items-center justify-center">
                                    <input type="number"
                                           min="0"
                                           max="1000000"
                                           name="qty[{{ $p->id }}][{{ $b->id }}]"
                                           value="{{ old("qty.{$p->id}.{$b->id}") }}"
                                           x-model.number="inputs['{{ $p->id }}']['{{ $b->id }}']"
                                           placeholder="0"
                                           class="w-24 sm:w-28 text-center font-bold text-stone-800 bg-stone-50/70 hover:bg-white focus:bg-white border border-stone-300 hover:border-stone-400 focus:border-[#a9633a] rounded-xl px-2.5 py-1.5 text-sm transition-all shadow-sm focus:outline-none focus:ring-4 focus:ring-[#fbd9a0]/60 placeholder:text-stone-300"
                                           :class="{ 'border-[#a9633a] bg-amber-50/40 ring-1 ring-[#a9633a]/30': inputs['{{ $p->id }}']['{{ $b->id }}'] > 0 }">
                                </div>
                            </td>
                        @endforeach

                        {{-- Total Kirim Real-Time --}}
                        <td class="td py-3 px-3 text-center align-middle bg-stone-50/50">
                            <span class="text-xs font-bold text-stone-700"
                                  x-text="getRowTotal('{{ $p->id }}') > 0 ? getRowTotal('{{ $p->id }}') + ' pcs' : '-'">
                            </span>
                        </td>

                        {{-- Sisa Gudang Real-Time --}}
                        <td class="td py-3 px-3 text-center align-middle bg-stone-50/50">
                            <template x-if="getRowRemaining('{{ $p->id }}') >= 0">
                                <span class="text-xs font-semibold text-stone-600"
                                      x-text="getRowRemaining('{{ $p->id }}') + ' pcs'">
                                </span>
                            </template>
                            <template x-if="getRowRemaining('{{ $p->id }}') < 0">
                                <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600 ring-1 ring-inset ring-red-600/20"
                                      title="Alokasi melebihi stok yang ada di gudang pusat!">
                                    Kurang <span x-text="Math.abs(getRowRemaining('{{ $p->id }}'))"></span>
                                </span>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + $branches->count() }}" class="td py-12 text-center text-stone-400">
                            Belum ada produk aktif yang tersedia untuk didistribusikan.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Catatan & Aksi Simpan --}}
    <div class="card p-5 max-w-2xl">
        <div class="space-y-4">
            <div>
                <label class="label font-semibold text-stone-700">Catatan Pengiriman (Opsional)</label>
                <input name="catatan"
                       value="{{ old('catatan') }}"
                       maxlength="150"
                       placeholder="Misal: Pengiriman stok batch pagi, restock akhir pekan, dsb."
                       class="input">
            </div>

            {{-- Ringkasan Alokasi --}}
            <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50 p-3 text-xs text-stone-600">
                <div>
                    <span>Total Distribusi Keseluruhan: </span>
                    <strong class="text-sm font-bold text-[#552918]" x-text="getGrandTotal() + ' pcs'">0 pcs</strong>
                </div>
                <div x-show="hasAnyOverStock()" x-cloak class="text-red-600 font-semibold flex items-center gap-1">
                    <x-icon name="alert" class="h-4 w-4" />
                    <span>Ada alokasi yang melebihi stok gudang!</span>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="btn-primary flex items-center gap-2"
                        :disabled="busy || hasAnyOverStock()">
                    <x-icon name="truck" class="h-4 w-4" />
                    <span x-text="busy ? 'Mengirim...' : 'Kirim Distribusi'">Kirim Distribusi</span>
                </button>
                <a href="{{ route('distributions.index') }}" class="btn-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>

<script>
function distributionForm() {
    return {
        busy: false,
        stocks: {
            @foreach ($products as $p)
                '{{ $p->id }}': {{ (int) ($gudang[$p->id] ?? 0) }},
            @endforeach
        },
        inputs: {
            @foreach ($products as $p)
                '{{ $p->id }}': {
                    @foreach ($branches as $b)
                        '{{ $b->id }}': {{ old("qty.{$p->id}.{$b->id}") !== null && old("qty.{$p->id}.{$b->id}") !== '' ? (int) old("qty.{$p->id}.{$b->id}") : "''" }},
                    @endforeach
                },
            @endforeach
        },
        getRowTotal(productId) {
            if (!this.inputs[productId]) return 0;
            let sum = 0;
            for (let bId in this.inputs[productId]) {
                let val = parseInt(this.inputs[productId][bId], 10);
                if (!isNaN(val) && val > 0) {
                    sum += val;
                }
            }
            return sum;
        },
        getRowRemaining(productId) {
            let available = this.stocks[productId] || 0;
            return available - this.getRowTotal(productId);
        },
        getGrandTotal() {
            let total = 0;
            for (let pId in this.stocks) {
                total += this.getRowTotal(pId);
            }
            return total;
        },
        hasAnyOverStock() {
            for (let pId in this.stocks) {
                if (this.getRowRemaining(pId) < 0) {
                    return true;
                }
            }
            return false;
        }
    };
}
</script>
@endsection
