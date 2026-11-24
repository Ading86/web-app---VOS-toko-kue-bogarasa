@extends('layouts.app')
@section('title', 'Kasir (POS)')
@section('heading', 'Kasir (POS)')
@section('breadcrumb', 'Operasional / POS · ' . (auth()->user()->branch?->nama_cabang))
@section('content')
<div x-data="pos(@js($products), @js($categories))" x-init="init()" class="grid gap-4 lg:grid-cols-3">
    {{-- Daftar produk --}}
    <div class="space-y-3 lg:col-span-2">
        <div class="card flex flex-col gap-3 p-3">
            <div class="relative"><x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" /><input x-model="search" type="search" placeholder="Cari produk (nama / kode)" class="input pl-10"></div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="cat = ''" class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition" :class="cat === '' ? 'border-brand-800 bg-brand-800 text-white' : 'border-stone-300 bg-white hover:bg-stone-50'">Semua</button>
                <template x-for="c in categories" :key="c.id">
                    <button type="button" @click="cat = c.id" class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition"
                            :class="cat === c.id ? 'border-brand-800 bg-brand-800 text-white' : 'border-stone-300 bg-white hover:bg-stone-50'" x-text="c.nama_kategori"></button>
                </template>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            <template x-for="p in filtered" :key="p.id">
                <button type="button" @click="add(p)" :disabled="p.stok <= 0"
                        class="card p-3 text-left transition hover:border-brand-400 disabled:cursor-not-allowed disabled:opacity-50">
                    <template x-if="p.gambar"><img :src="p.gambar" alt="" class="mb-2 h-24 w-full rounded-lg object-cover"></template>
                    <template x-if="!p.gambar"><div class="mb-2 flex h-24 items-center justify-center rounded-xl bg-brand-50 text-brand-300"><x-icon name="image" class="h-8 w-8" /></div></template>
                    <div class="text-sm font-medium leading-tight" x-text="p.nama"></div>
                    <div class="mt-1 text-sm text-brand-700" x-text="rp(p.harga)"></div>
                    <div class="text-xs" :class="p.stok <= 0 ? 'text-red-600' : 'text-stone-500'" x-text="p.stok <= 0 ? 'Stok habis' : 'Stok: ' + p.stok"></div>
                </button>
            </template>
        </div>
        <div x-show="filtered.length === 0" x-cloak class="card p-8 text-center text-stone-400">Produk tidak ditemukan.</div>
    </div>

    {{-- Keranjang --}}
    <div class="card h-fit space-y-3 p-4 lg:sticky lg:top-20">
        <h2 class="font-semibold text-brand-900">Keranjang</h2>
        <div x-show="items.length === 0" x-cloak class="py-6 text-center text-sm text-stone-400">Belum ada item.</div>
        <div class="max-h-72 space-y-2 overflow-y-auto">
            <template x-for="i in items" :key="i.p.id">
                <div class="flex items-center justify-between gap-2 border-b border-stone-100 pb-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium" x-text="i.p.nama"></div>
                        <div class="text-xs text-stone-500" x-text="rp(i.p.harga) + ' × ' + i.qty"></div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="dec(i.p.id)" class="btn-secondary btn-sm px-2" aria-label="Kurangi"><x-icon name="minus" class="h-3.5 w-3.5" /></button>
                        <span class="w-6 text-center text-sm" x-text="i.qty"></span>
                        <button type="button" @click="add(i.p)" class="btn-secondary btn-sm px-2" aria-label="Tambah"><x-icon name="plus" class="h-3.5 w-3.5" /></button>
                        <button type="button" @click="remove(i.p.id)" class="rounded-lg p-1.5 text-stone-400 transition hover:bg-red-50 hover:text-red-600" title="Hapus" aria-label="Hapus item"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex items-center justify-between border-t border-stone-200 pt-3">
            <span class="text-sm text-stone-600">Total</span>
            <span class="text-xl font-bold text-brand-900" x-text="rp(total)"></span>
        </div>

        <div>
            <label class="label">Metode pembayaran</label>
            <select x-model="metode" class="input">
                <option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option>
            </select>
        </div>
        <div x-show="metode === 'tunai'" x-cloak>
            <label class="label">Uang diterima</label>
            <input x-model="bayar" type="number" min="0" inputmode="numeric" class="input" placeholder="0">
            <button type="button" @click="bayar = total" class="mt-1 text-xs text-brand-700 underline">Uang pas</button>
            <div class="mt-2 flex justify-between text-sm"><span>Kembalian</span><span class="font-semibold" x-text="rp(kembalian)"></span></div>
        </div>
        <div x-show="metode !== 'tunai'" x-cloak>
            <label class="label">Referensi pembayaran (opsional)</label>
            <input x-model="referensi" type="text" maxlength="100" class="input" placeholder="No. referensi / nama pengirim">
        </div>

        <div x-show="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" x-text="error"></div>
        <button type="button" @click="checkout()" :disabled="!canPay" class="btn-primary w-full py-3 text-base">
            <span x-show="!loading">Bayar &amp; simpan</span><span x-show="loading" x-cloak>Memproses…</span>
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pos(products, categories) {
    return {
        products, categories, search: '', cat: '', cart: {}, metode: 'tunai', bayar: '', referensi: '', loading: false, error: '', key: '',
        init() { this.key = this.uuid(); },
        uuid() {
            if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); });
        },
        rp(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0); },
        get filtered() {
            const s = this.search.toLowerCase();
            return this.products.filter(p => (!this.cat || p.kategori_id === this.cat) && (!s || p.nama.toLowerCase().includes(s) || (p.kode || '').toLowerCase().includes(s)));
        },
        get items() { return Object.entries(this.cart).map(([id, qty]) => { const p = this.products.find(x => x.id === id); return { p, qty, sub: p.harga * qty }; }); },
        get total() { return this.items.reduce((a, i) => a + i.sub, 0); },
        get kembalian() { return this.metode === 'tunai' ? Math.max(0, (Number(this.bayar) || 0) - this.total) : 0; },
        get canPay() { return this.items.length > 0 && !this.loading && (this.metode !== 'tunai' || (Number(this.bayar) || 0) >= this.total); },
        add(p) {
            const q = (this.cart[p.id] || 0) + 1;
            if (q > p.stok) { this.error = 'Stok ' + p.nama + ' hanya ' + p.stok + '.'; return; }
            this.error = ''; this.cart = { ...this.cart, [p.id]: q };
        },
        dec(id) { const q = (this.cart[id] || 0) - 1; const c = { ...this.cart }; if (q <= 0) delete c[id]; else c[id] = q; this.cart = c; },
        remove(id) { const c = { ...this.cart }; delete c[id]; this.cart = c; },
        async checkout() {
            if (!this.canPay) return;
            this.loading = true; this.error = '';
            try {
                const res = await fetch(@js(route('pos.checkout')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        items: this.items.map(i => ({ product_id: i.p.id, qty: i.qty })),
                        metode: this.metode,
                        jumlah_bayar: this.metode === 'tunai' ? Number(this.bayar) : this.total,
                        referensi: this.referensi || null,
                        idempotency_key: this.key,   // key tetap saat retry => tidak ada transaksi ganda
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.error = data.message || 'Gagal menyimpan transaksi.'; this.loading = false; return; }
                window.location = data.redirect;
            } catch (e) {
                this.error = 'Koneksi bermasalah. Tekan Bayar lagi (aman, tidak akan tercatat ganda).'; this.loading = false;
            }
        },
    };
}
</script>
@endpush
