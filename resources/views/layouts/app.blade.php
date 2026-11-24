@php
    $u = auth()->user();
    $isOwner = $u->isOwner();
    $pusat = \App\Models\Branch::pusat();

    // Menu dikelompokkan agar mudah dipindai: [label, route, permission, pola route aktif, ikon, kondisi tambahan]
    // Format menu dengan URL params: [label, route, permission, pola aktif, ikon, kondisi, params]
    $ownerReviewParams = ['status' => 'diajukan'];
    $sections = [
        'Operasional' => [
            [($isOwner ? 'Dashboard VOS' : 'Dashboard'), 'dashboard', 'dashboard.view', 'dashboard*', 'layout'],
            ['Kasir (POS)', 'pos.index', 'transactions.create', 'pos*', 'cart'],
            [($isOwner ? 'Persediaan' : 'Stok Cabang'), 'inventory.index', 'inventory.view', 'inventory*', 'package'],
            ['Penerimaan Stok', 'stock-receipts.index', 'inventory.manage', 'stock-receipts*', 'download', $isOwner || ($pusat && $u->branch_id === $pusat->id)],
            [($isOwner ? 'Distribusi Stok' : 'Penerimaan Distribusi'), 'distributions.index', 'distributions.view', 'distributions*', 'truck'],
            ['Persetujuan Stok', 'stock-requests.index', 'stock_requests.approve', 'stock-requests*', 'check-circle', $isOwner],
            ['Permintaan Stok', 'stock-requests.index', 'stock_requests.view', 'stock-requests*', 'inbox', ! $isOwner],
            [($isOwner ? 'Transaksi' : 'Riwayat Transaksi'), 'transactions.index', 'transactions.view', 'transactions*', 'file-text'],
            ['Laporan', 'reports.index', 'reports.view', 'reports*', 'bar-chart'],
        ],
        'Master data' => [
            ['Cabang', 'branches.index', 'branches.view', 'branches*', 'map-pin'],
            ['Produk', 'products.index', 'products.view', 'products*', 'bag'],
            ['Kategori', 'categories.index', 'categories.view', 'categories*', 'tag'],
        ],
        'Administrasi' => [
            ['Pengguna', 'users.index', 'users.view', 'users*', 'users'],
            ['Role & Permission', 'roles.index', 'permissions.manage', 'roles*', 'shield'],
            ['Audit Trail', 'audit.index', 'auditlogs.view', 'audit*', 'clipboard'],
            ['Pengaturan Akun', 'password.change', null, 'password*', 'lock'],
        ],
    ];

    $lowStock = ($u->can('inventory.view') && \Illuminate\Support\Facades\Route::has('inventory.index'))
        ? \App\Models\BranchStock::whereColumn('jumlah_stok', '<=', 'stok_minimum')->count()
        : 0;

    // Kiriman distribusi yang masih dalam perjalanan dan menunggu konfirmasi.
    $pendingReceipts = ($u->can('distributions.receive') && \Illuminate\Support\Facades\Route::has('distributions.index'))
        ? \App\Models\StockDistributionDetail::where('status', 'dikirim')
            ->when(! $isOwner, fn ($q) => $q->where('branch_tujuan_id', $u->branch_id ?? '-'))
            ->distinct()->count('distribution_id')
        : 0;

    // Permintaan stok yang menunggu review / proses pengiriman
    $pendingRequests = 0;
    if (\Illuminate\Support\Facades\Route::has('stock-requests.index')) {
        if ($isOwner && $u->can('stock_requests.approve')) {
            $pendingRequests = \App\Models\StockRequest::where('status', \App\Models\StockRequest::STATUS_DIAJUKAN)->count();
        } elseif (! $isOwner && ($u->isWarehouse() || ($pusat && $u->branch_id === $pusat->id)) && $u->can('stock_requests.process')) {
            $pendingRequests = \App\Models\StockRequest::where('status', \App\Models\StockRequest::STATUS_DISETUJUI)->count();
        }
    }

    $initial = mb_strtoupper(mb_substr($u->name, 0, 1));
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title', 'Dashboard') · Boga Rasa</title>
    @stack('head')
</head>

<body class="bg-[#F8F1E7] text-stone-800 antialiased"
      x-data="{ open: false }"
      @keydown.escape.window="open = false">

    @include('partials.toast')

    {{-- Overlay menu (mobile) --}}
    <div x-show="open" x-cloak x-transition.opacity
         @click="open = false"
         class="fixed inset-0 z-30 bg-stone-900/40 backdrop-blur-sm lg:hidden"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-[#633B28] bg-[#4A2618] transition-transform duration-200 lg:w-64 lg:translate-x-0"
           :class="open ? 'translate-x-0' : '-translate-x-full'"
           aria-label="Menu utama">

        <div class="flex items-center justify-between px-5 py-4">
            <a href="{{ route('dashboard') }}" class="block" aria-label="Boga Rasa, ke dashboard">
                <img src="{{ asset('images/logo-sm.png') }}"
                     alt="Boga Rasa Cake and Food"
                     class="h-14 w-auto">
            </a>

            <button type="button"
                    class="rounded-lg p-1.5 text-[#D4B9A1] hover:bg-[#633B28] lg:hidden"
                    @click="open = false"
                    aria-label="Tutup menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4 pt-1">
            @foreach ($sections as $title => $items)
                @php
                    $visible = collect($items)->filter(
                        fn ($i) => \Illuminate\Support\Facades\Route::has($i[1])
                            && (! $i[2] || $u->can($i[2]))
                            && (! isset($i[5]) || $i[5] === true)
                    );
                @endphp

                @if ($visible->isNotEmpty())
                    <div>
                        <div class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-[#D4B9A1]">
                            {{ $title }}
                        </div>

                        <div class="space-y-0.5">
                            @foreach ($visible as [$label, $route, $perm, $pattern, $icon])
                                @php
                                    $active = request()->routeIs($pattern);
                                @endphp

                                <a href="{{ route($route) }}"
                                   @if ($active) aria-current="page" @endif
                                   @click="open = false"
                                   class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition {{ $active ? 'bg-[#70452F] font-semibold text-[#FFD58A]' : 'text-[#F5E6D3] hover:bg-[#633B28] hover:text-white' }}">

                                    @if ($active)
                                        <span class="absolute -left-3 top-1/2 h-6 w-1 -translate-y-1/2 rounded-r-full bg-[#E6A23C]"></span>
                                    @endif

                                    <x-icon :name="$icon"
                                            class="h-5 w-5 shrink-0 {{ $active ? 'text-[#FFD58A]' : 'text-[#D4B9A1] group-hover:text-[#FFD58A]' }}" />

                                    <span class="truncate">{{ $label }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        {{-- Akun --}}
        <div class="border-t border-[#633B28] p-3">
            <div class="flex items-center gap-3 rounded-xl bg-[#633B28] p-2.5">

                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E6A23C] text-sm font-semibold text-[#4A2618]">
                    {{ $initial }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-white">
                        {{ $u->name }}
                    </div>

                    <div class="truncate text-xs text-[#D4B9A1]">
                        {{ $u->getRoleNames()->implode(', ') }} · {{ $u->branch?->nama_cabang ?? 'Semua cabang' }}
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit"
                            class="rounded-lg p-2 text-[#D4B9A1] transition hover:bg-[#4A2618] hover:text-white"
                            title="Keluar"
                            aria-label="Keluar">
                        <x-icon name="log-out" class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Konten utama --}}
    <div class="lg:pl-64">

        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-[#E8D8C5] bg-[#FFFDF9]/95 px-4 py-3 backdrop-blur sm:px-6">

            <div class="flex min-w-0 items-center gap-3">
                <button type="button"
                        class="rounded-lg border border-[#D9C3AE] p-2 text-[#633B28] hover:bg-[#F4E6D6] lg:hidden"
                        @click="open = true"
                        aria-label="Buka menu">
                    <x-icon name="menu" class="h-5 w-5" />
                </button>

                <div class="truncate text-sm text-[#8A7565]">
                    @yield('breadcrumb', '')
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2.5">
                {{-- Jam Real-Time WIB --}}
                <div x-data="liveWibClock()" x-init="start()" class="flex items-center gap-2 rounded-xl border border-[#D9C3AE] bg-[#F8F1E7] px-3 py-1.5 text-xs text-[#552918] shadow-sm">
                    <x-icon name="clock" class="h-4 w-4 text-[#8A4A2B]" />
                    <span x-text="dateStr" class="font-medium text-stone-700"></span>
                    <span class="text-[#D4B9A1]">|</span>
                    <span x-text="timeStr" class="font-mono font-bold text-[#4A2618]"></span>
                    <span class="rounded bg-[#E6A23C]/30 px-1 py-0.5 text-[10px] font-bold text-[#6E3820]">WIB</span>
                </div>

                @if ($pendingRequests > 0)
                    <a href="{{ route('stock-requests.index', ['status' => $isOwner ? 'diajukan' : 'disetujui']) }}"
                       class="badge-warn gap-1.5 py-1 hover:bg-[#F4E6D6]"
                       title="{{ $isOwner ? 'Permintaan stok menunggu persetujuan Anda' : 'Permintaan stok siap diproses Warehouse' }}">
                        <x-icon name="inbox" class="h-3.5 w-3.5" />
                        {{ $pendingRequests }}
                        <span class="hidden sm:inline"> {{ $isOwner ? 'permintaan perlu review' : 'permintaan siap kirim' }}</span>
                    </a>
                @endif

                @if ($pendingReceipts > 0)
                    <a href="{{ route('distributions.index', ['status' => 'dikirim']) }}"
                       class="badge-warn gap-1.5 py-1 hover:bg-[#F4E6D6]"
                       title="Kiriman menunggu konfirmasi">
                        <x-icon name="truck" class="h-3.5 w-3.5" />
                        {{ $pendingReceipts }}
                        <span class="hidden sm:inline"> kiriman dalam perjalanan</span>
                    </a>
                @endif

                @if ($lowStock > 0)
                    <a href="{{ route('inventory.index', ['low' => 1]) }}"
                       class="badge-bad gap-1.5 py-1 hover:bg-red-100"
                       title="Stok menipis">
                        <x-icon name="bell" class="h-3.5 w-3.5" />
                        {{ $lowStock }}
                        <span class="hidden sm:inline"> stok menipis</span>
                    </a>
                @endif
            </div>
        </header>

        {{-- Isi halaman --}}
        <main class="mx-auto max-w-1400px p-4 sm:p-6">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#4A2618]">
                        @yield('heading', 'Dashboard')
                    </h1>

                    @hasSection('subtitle')
                        <p class="mt-1 text-sm text-[#8A7565]">
                            @yield('subtitle')
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @yield('actions')
                </div>
            </div>

            @include('partials.errors')

            @yield('content')
        </main>

        <footer class="px-6 pb-6 pt-2 text-center text-xs text-[#9B8573]">
            Boga Rasa · Cake and Food
        </footer>
    </div>

    @include('partials.confirm')
    @stack('scripts')

    {{-- Script Real-Time Jam WIB --}}
    <script>
    function liveWibClock() {
        return {
            dateStr: '',
            timeStr: '',
            start() {
                this.tick();
                setInterval(() => this.tick(), 1000);
            },
            tick() {
                const now = new Date();
                this.dateStr = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                }).format(now);
                this.timeStr = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).format(now).replace(/\./g, ':');
            }
        };
    }
    </script>
</body>
</html>
