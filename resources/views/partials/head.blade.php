<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#552918">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

{{-- Fallback CSS dasar — aktif sebelum Tailwind selesai parse (mencegah FOUC & SVG purple boxes) --}}
<style id="base-fallback">
  [x-cloak] { display: none !important; }

  /* SVG icon — ukuran tetap, warna mengikuti teks (mencegah purple SVG besar) */
  svg {
    display: inline-block; vertical-align: middle;
    width: 20px; height: 20px; max-width: 20px; max-height: 20px;
    color: inherit; fill: none; stroke: currentColor; overflow: visible;
  }
  aside svg, nav svg { width: 20px !important; height: 20px !important; flex-shrink: 0; }

  body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #F8F1E7; color: #1c1917; }
  tbody tr:hover > td { background-color: #fcf9f4; }
  ::selection { background: #fbd9a0; color: #3e1d10; }
</style>

{{-- Tailwind CDN Play (lokal dulu, onerror fallback CDN) --}}
<script src="{{ asset('vendor/tailwind.min.js') }}"
        onerror="this.onerror=null;this.src='https://cdn.tailwindcss.com/3.4.17'"></script>

{{-- Konfigurasi Tailwind — set SETELAH script dimuat agar Proxy reaktif menangkap perubahan --}}
<script>
(function() {
    var config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif']
                },
                colors: {
                    brand: {
                        50:'#fbf5ee', 100:'#f6e9dc', 200:'#ecd3bc', 300:'#ddb48f',
                        400:'#c98b5e', 500:'#a9633a', 600:'#8a4a2b', 700:'#6e3820',
                        800:'#552918', 900:'#3e1d10'
                    },
                    gold: {
                        50:'#fff8ea', 100:'#fdebc8', 200:'#fbd9a0', 300:'#f8c078',
                        400:'#f0a957', 500:'#e3913a', 600:'#c7752a', 700:'#a25a24'
                    },
                    cream: '#faf6ef'
                },
                boxShadow: {
                    card: '0 1px 2px rgba(62,29,16,.04), 0 2px 8px rgba(62,29,16,.05)'
                }
            }
        }
    };
    if (typeof tailwind !== 'undefined') {
        // Tailwind CDN Play sudah load — set config melalui Proxy reaktif
        tailwind.config = config;
    }
})();
</script>

{{-- Komponen CSS Tailwind --}}
<style type="text/tailwindcss">
@layer base {
  html { -webkit-text-size-adjust: 100%; }
  body { @apply font-sans; }
  :focus-visible { @apply outline-2 outline-offset-2 outline-gold-500; }
}
@layer components {
  .card { @apply bg-white rounded-2xl border border-stone-200/80 shadow-card; }
  .btn-primary, .btn-secondary, .btn-danger { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50; }
  .btn-primary { @apply bg-brand-800 text-white shadow-sm hover:bg-brand-900; }
  .btn-secondary { @apply bg-white text-stone-700 border border-stone-300 hover:bg-stone-50 hover:border-stone-400; }
  .btn-danger { @apply bg-red-600 text-white shadow-sm hover:bg-red-700; }
  .btn-sm { @apply px-3 py-1.5 text-xs rounded-lg; }
  .input { @apply w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm text-stone-800 placeholder:text-stone-400 transition focus:border-gold-500 focus:outline-none focus:ring-4 focus:ring-gold-200/60 disabled:bg-stone-100; }
  .label { @apply block text-sm font-medium text-stone-700 mb-1.5; }
  .th { @apply px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-stone-500 bg-stone-50 whitespace-nowrap; }
  .td { @apply px-4 py-3.5 text-sm text-stone-700 whitespace-nowrap; }
  .badge-ok, .badge-warn, .badge-bad, .badge-mute { @apply inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset; }
  .badge-ok { @apply bg-emerald-50 text-emerald-700 ring-emerald-600/20; }
  .badge-warn { @apply bg-gold-50 text-gold-700 ring-gold-600/25; }
  .badge-bad { @apply bg-red-50 text-red-700 ring-red-600/20; }
  .badge-mute { @apply bg-stone-100 text-stone-600 ring-stone-500/15; }
  .pg { @apply inline-flex min-w-[2.25rem] items-center justify-center rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm text-stone-700 transition hover:bg-stone-50; }
  .pg-on { @apply border-brand-800 bg-brand-800 text-white hover:bg-brand-800; }
  .pg-off { @apply cursor-default border-stone-200 bg-stone-50 text-stone-400 hover:bg-stone-50; }
}
</style>

{{-- Alpine.js (lokal dulu, onerror fallback CDN) --}}
<script defer src="{{ asset('vendor/alpine.min.js') }}"
        onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js'"></script>
