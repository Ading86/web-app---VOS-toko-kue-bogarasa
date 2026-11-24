<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title', 'Masuk') · Boga Rasa</title>
</head>
<body class="min-h-screen bg-cream text-stone-800 antialiased">
    @include('partials.toast')
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel merek (desktop) --}}
        <div class="relative hidden overflow-hidden bg-brand-800 lg:flex lg:flex-col lg:items-center lg:justify-center lg:p-12">
            <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-gold-300/10"></div>
            <div class="absolute -bottom-32 -right-16 h-96 w-96 rounded-full bg-gold-300/10"></div>
            <div class="relative w-full max-w-md rounded-3xl bg-cream p-8 shadow-2xl">
                <img src="{{ asset('images/logo.png') }}" alt="Boga Rasa Cake and Food" class="mx-auto h-auto w-full">
            </div>
            <p class="relative mt-8 max-w-sm text-center text-sm leading-relaxed text-brand-200">Sistem manajemen toko kue: kasir, stok antar cabang, distribusi, dan laporan dalam satu tempat.</p>
        </div>

        {{-- Form --}}
        <div class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center lg:hidden">
                    <img src="{{ asset('images/logo-sm.png') }}" alt="Boga Rasa Cake and Food" class="mx-auto h-24 w-auto">
                </div>
                <div class="card p-6 sm:p-8">
                    @include('partials.errors')
                    @yield('content')
                </div>
                <p class="mt-6 text-center text-xs text-stone-400">Boga Rasa · Cake and Food</p>
            </div>
        </div>
    </div>
    @include('partials.confirm')
</body>
</html>
