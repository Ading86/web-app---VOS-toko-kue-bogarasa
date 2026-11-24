@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', $isOwner ? 'Dashboard VOS' : 'Dashboard Cabang')
@section('breadcrumb', 'Monitoring / ' . ($isOwner ? 'Semua cabang' : (auth()->user()->branch?->nama_cabang)))
@push('head')<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>@endpush

@section('content')
<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" x-data="{ p: @js($periode) }">
    <select name="periode" x-model="p" class="input">
        @foreach ($presets as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
    </select>
    <input type="date" name="date_from" value="{{ $dateFrom }}" class="input" x-show="p === 'custom'" x-cloak>
    <input type="date" name="date_to" value="{{ $dateTo }}" class="input" x-show="p === 'custom'" x-cloak>
    @if ($isOwner)
        <select name="branch_id" class="input"><option value="">Semua cabang</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') === $b->id)>{{ $b->nama_cabang }}</option>@endforeach</select>
    @endif
    <button class="btn-primary" type="submit">Terapkan</button>
</form>

<div x-data="dash(@js($data), @js($live), @js(route('dashboard.version')), @js(route('dashboard.data')))" x-init="init()">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-stone-500">
        <span x-text="d.label"></span>
        <span x-show="live" x-cloak class="inline-flex items-center gap-2"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span>Live · diperbarui <span x-text="d.generated_at"></span></span>
    </div>

    {{-- KPI --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ([['Total omzet', 'banknote', 'rp(d.kpi.omzet)'], ['Total transaksi', 'file-text', 'num(d.kpi.transaksi)'], ['Produk terjual', 'bag', 'num(d.kpi.terjual)'], ['Rata-rata transaksi', 'bar-chart', 'rp(d.kpi.rata)'], ['Produk aktif', 'package', 'num(d.kpi.produk_aktif)']] as [$kLabel, $kIcon, $kExpr])
            <div class="card flex items-center gap-3 p-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold-100 text-gold-700"><x-icon :name="$kIcon" class="h-5 w-5" /></div>
                <div class="min-w-0"><div class="text-xs text-stone-500">{{ $kLabel }}</div><div class="truncate text-lg font-bold text-brand-900" x-text="{{ $kExpr }}"></div></div>
            </div>
        @endforeach
        <div class="card flex items-center gap-3 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="d.kpi.stok_menipis > 0 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"><x-icon name="bell" class="h-5 w-5" /></div>
            <div class="min-w-0"><div class="text-xs text-stone-500">Stok menipis</div><div class="truncate text-lg font-bold" :class="d.kpi.stok_menipis > 0 ? 'text-red-600' : 'text-emerald-700'" x-text="num(d.kpi.stok_menipis)"></div></div>
        </div>
    </div>

    {{-- Grafik penjualan --}}
    <div class="mb-4 grid gap-4 lg:grid-cols-3">
        <div class="card p-4 lg:col-span-2"><h3 class="mb-2 text-sm font-semibold text-brand-900">Penjualan harian (omzet)</h3><div class="h-64"><canvas x-ref="cDaily"></canvas></div></div>
        <div class="card p-4"><h3 class="mb-2 text-sm font-semibold text-brand-900">Jumlah transaksi harian</h3><div class="h-64"><canvas x-ref="cTrx"></canvas></div></div>
        <div class="card p-4"><h3 class="mb-2 text-sm font-semibold text-brand-900">Penjualan mingguan (12 minggu)</h3><div class="h-64"><canvas x-ref="cWeekly"></canvas></div></div>
        <div class="card p-4"><h3 class="mb-2 text-sm font-semibold text-brand-900">Penjualan bulanan (12 bulan)</h3><div class="h-64"><canvas x-ref="cMonthly"></canvas></div></div>
        <div class="card p-4"><h3 class="mb-2 text-sm font-semibold text-brand-900">Produk terlaris (jumlah)</h3><div class="h-64"><canvas x-ref="cTop"></canvas></div></div>
        @if ($isOwner)
        <div class="card p-4 lg:col-span-3"><h3 class="mb-2 text-sm font-semibold text-brand-900">Perbandingan omzet antar cabang</h3><div class="h-64"><canvas x-ref="cBranch"></canvas></div></div>
        @endif
    </div>

    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        @if ($isOwner)
        <div class="card overflow-x-auto"><h3 class="px-4 pt-4 text-sm font-semibold text-brand-900">Performa cabang</h3>
            <table class="min-w-full"><thead><tr><th class="th">Cabang</th><th class="th text-right">Transaksi</th><th class="th text-right">Terjual</th><th class="th text-right">Omzet</th><th class="th text-right">Rata-rata</th></tr></thead>
                <tbody class="divide-y divide-stone-100"><template x-for="b in d.branches" :key="b.branch_id"><tr>
                    <td class="td font-medium" x-text="b.nama"></td><td class="td text-right" x-text="num(b.trx)"></td><td class="td text-right" x-text="num(b.qty)"></td>
                    <td class="td text-right" x-text="rp(b.omzet)"></td><td class="td text-right" x-text="rp(b.rata)"></td></tr></template></tbody></table></div>
        @endif
        <div class="card overflow-x-auto"><h3 class="px-4 pt-4 text-sm font-semibold text-brand-900">Informasi stok {{ $isOwner ? 'setiap cabang' : 'cabang' }}</h3>
            <table class="min-w-full"><thead><tr><th class="th">Cabang</th><th class="th text-right">Total stok</th><th class="th text-right">Item menipis</th></tr></thead>
                <tbody class="divide-y divide-stone-100"><template x-for="s in d.stok" :key="s.kode"><tr>
                    <td class="td font-medium" x-text="s.nama"></td><td class="td text-right" x-text="num(s.total_stok)"></td>
                    <td class="td text-right"><span :class="s.menipis > 0 ? 'badge-warn' : 'badge-ok'" x-text="s.menipis"></span></td></tr></template></tbody></table></div>
        <div class="card overflow-x-auto"><h3 class="px-4 pt-4 text-sm font-semibold text-brand-900">Stok menipis / habis</h3>
            <table class="min-w-full"><thead><tr><th class="th">Produk</th><th class="th">Cabang</th><th class="th text-right">Stok</th><th class="th text-right">Min</th></tr></thead>
                <tbody class="divide-y divide-stone-100"><template x-for="(i, ix) in d.low_items" :key="ix"><tr>
                    <td class="td" x-text="i.produk"></td><td class="td" x-text="i.cabang"></td><td class="td text-right font-semibold" :class="i.stok === 0 ? 'text-red-600' : 'text-amber-600'" x-text="i.stok"></td><td class="td text-right" x-text="i.minimum"></td></tr></template>
                    <tr x-show="d.low_items.length === 0" x-cloak><td colspan="4" class="td py-6 text-center text-stone-400">Semua stok aman.</td></tr></tbody></table></div>
        <div class="card overflow-x-auto"><h3 class="px-4 pt-4 text-sm font-semibold text-brand-900">Produk terlaris</h3>
            <table class="min-w-full"><thead><tr><th class="th">Produk</th><th class="th text-right">Terjual</th><th class="th text-right">Omzet</th></tr></thead>
                <tbody class="divide-y divide-stone-100"><template x-for="t in d.top" :key="t.product_id"><tr>
                    <td class="td" x-text="t.nama"></td><td class="td text-right" x-text="num(t.qty)"></td><td class="td text-right" x-text="rp(t.omzet)"></td></tr></template>
                    <tr x-show="d.top.length === 0" x-cloak><td colspan="3" class="td py-6 text-center text-stone-400">Belum ada penjualan pada periode ini.</td></tr></tbody></table></div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card overflow-x-auto"><h3 class="px-4 pt-4 text-sm font-semibold text-brand-900">Transaksi terbaru</h3>
            <table class="min-w-full"><thead><tr><th class="th">Kode</th><th class="th">Waktu</th><th class="th text-right">Total</th><th class="th">Status</th></tr></thead>
                <tbody class="divide-y divide-stone-100"><template x-for="t in d.recent" :key="t.kode"><tr>
                    <td class="td font-mono text-xs"><a :href="t.url || '#'" class="text-brand-700 underline" x-text="t.kode"></a></td><td class="td" x-text="t.waktu"></td>
                    <td class="td text-right" x-text="rp(t.total)"></td><td class="td"><span :class="t.status === 'selesai' ? 'badge-ok' : 'badge-bad'" x-text="t.status"></span></td></tr></template>
                    <tr x-show="d.recent.length === 0" x-cloak><td colspan="4" class="td py-6 text-center text-stone-400">Belum ada transaksi.</td></tr></tbody></table></div>
        <div class="card overflow-x-auto"><h3 class="px-4 pt-4 text-sm font-semibold text-brand-900">Aktivitas pengguna terbaru</h3>
            <table class="min-w-full"><thead><tr><th class="th">Waktu</th><th class="th">Pengguna</th><th class="th">Aktivitas</th></tr></thead>
                <tbody class="divide-y divide-stone-100"><template x-for="(a, ix) in d.activities" :key="ix"><tr>
                    <td class="td" x-text="a.waktu"></td><td class="td" x-text="a.user"></td><td class="td whitespace-normal" x-text="a.deskripsi"></td></tr></template>
                    <tr x-show="d.activities.length === 0" x-cloak><td colspan="3" class="td py-6 text-center text-stone-400">Belum ada aktivitas.</td></tr></tbody></table></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function dash(initial, live, versionUrl, dataUrl) {
    const charts = {};   // di luar state Alpine agar instance Chart.js tidak dibungkus proxy
    const BROWN = '#6e3820', TAN = '#f0a957';
    const copy = (o) => JSON.parse(JSON.stringify(o));
    const moneyTick = (v) => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v);

    return {
        d: copy(initial), live, version: initial.version,
        rp(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0)); },
        num(n) { return new Intl.NumberFormat('id-ID').format(n || 0); },
        init() {
            this.$nextTick(() => this.draw());
            if (this.live) setInterval(() => this.poll(), 20000);   // polling 20 detik
        },
        qs() { return window.location.search; },
        async poll() {
            if (document.hidden) return;
            try {
                const v = await (await fetch(versionUrl + this.qs(), { headers: { Accept: 'application/json' } })).json();
                if (v.version === this.version) return;
                const data = await (await fetch(dataUrl + this.qs(), { headers: { Accept: 'application/json' } })).json();
                this.d = copy(data); this.version = data.version; this.draw();
            } catch (e) { /* abaikan, coba lagi pada siklus berikutnya */ }
        },
        make(key, ref, type, labels, datasets, extra = {}) {
            if (!ref) return;
            if (charts[key]) {
                charts[key].data.labels = labels; charts[key].data.datasets.forEach((ds, i) => ds.data = datasets[i].data); charts[key].update(); return;
            }
            charts[key] = new Chart(ref.getContext('2d'), { type, data: { labels, datasets }, options: Object.assign({
                responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } },
            }, extra) });
        },
        draw() {
            const c = this.d.charts, money = { y: { beginAtZero: true, ticks: { callback: moneyTick } } };
            this.make('daily', this.$refs.cDaily, 'bar', c.daily.labels, [{ label: 'Omzet', data: c.daily.omzet, backgroundColor: BROWN }], { scales: money });
            this.make('trx', this.$refs.cTrx, 'line', c.daily.labels, [{ label: 'Transaksi', data: c.daily.trx, borderColor: BROWN, backgroundColor: TAN, tension: .3 }]);
            this.make('weekly', this.$refs.cWeekly, 'bar', c.weekly.labels, [{ label: 'Omzet', data: c.weekly.omzet, backgroundColor: TAN }], { scales: money });
            this.make('monthly', this.$refs.cMonthly, 'bar', c.monthly.labels, [{ label: 'Omzet', data: c.monthly.omzet, backgroundColor: BROWN }], { scales: money });
            this.make('top', this.$refs.cTop, 'bar', c.top.labels, [{ label: 'Terjual', data: c.top.qty, backgroundColor: TAN }], { indexAxis: 'y', scales: { x: { beginAtZero: true } } });
            this.make('branch', this.$refs.cBranch, 'bar', c.branches.labels, [{ label: 'Omzet', data: c.branches.omzet, backgroundColor: BROWN }], { scales: money });
        },
    };
}
</script>
@endpush
