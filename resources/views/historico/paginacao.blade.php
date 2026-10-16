@if ($paginator->hasPages())
    <nav aria-label="Páginas do histórico" class="grid gap-3">
        <p class="text-sm text-muted">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
            · {{ $paginator->total() }} mesas
        </p>

        <div class="flex flex-wrap items-center gap-2">
            @if (! $paginator->onFirstPage())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:navigate
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-coal/15 bg-white px-3 text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                    Anterior
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-muted">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" aria-label="Página {{ $page }}"
                                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl bg-coal px-3 text-sm font-bold text-cream">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" aria-label="Ir para a página {{ $page }}" wire:navigate
                                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-coal/15 bg-white px-3 text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:navigate
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-coal/15 bg-white px-3 text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                    Próxima
                </a>
            @endif
        </div>
    </nav>
@endif
