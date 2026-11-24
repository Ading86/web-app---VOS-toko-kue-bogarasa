@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-500">
            Menampilkan <span class="font-semibold text-stone-700">{{ $paginator->firstItem() }}</span> sampai
            <span class="font-semibold text-stone-700">{{ $paginator->lastItem() }}</span> dari
            <span class="font-semibold text-stone-700">{{ $paginator->total() }}</span> data
        </p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="pg pg-off">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg">Sebelumnya</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pg pg-off">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="pg pg-on">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pg" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg">Berikutnya</a>
            @else
                <span class="pg pg-off">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
