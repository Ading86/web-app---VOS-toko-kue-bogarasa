@foreach (['success' => ['check-circle', 'text-emerald-600', 'border-l-emerald-500'], 'error' => ['x-circle', 'text-red-600', 'border-l-red-500'], 'warning' => ['alert', 'text-gold-600', 'border-l-gold-500']] as $type => [$icon, $iconColor, $bar])
    @if (session($type))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             role="status" class="fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-stone-200 border-l-4 {{ $bar }} bg-white px-4 py-3 text-sm text-stone-700 shadow-lg">
            <x-icon :name="$icon" class="mt-0.5 h-5 w-5 shrink-0 {{ $iconColor }}" />
            <div class="flex-1">{{ session($type) }}</div>
            <button type="button" @click="show = false" class="text-stone-400 transition hover:text-stone-600" aria-label="Tutup"><x-icon name="x" class="h-4 w-4" /></button>
        </div>
    @endif
@endforeach
