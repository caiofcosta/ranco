@props(['titulo' => null])
@php($itens = app(\App\Navegacao\CaminhoMobile::class)->itens($titulo))
@if ($itens !== [])
    <nav aria-label="Caminho de navegação" class="mb-4 lg:hidden" data-mobile-breadcrumb>
        <ol class="flex min-h-11 flex-wrap items-center gap-x-2 text-xs">
            @foreach ($itens as $item)
                <li class="flex min-w-0 max-w-full items-center gap-2">
                    @unless ($loop->first)<flux:icon.chevron-right class="size-3 shrink-0 text-muted" aria-hidden="true" />@endunless
                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" wire:navigate class="inline-flex min-h-11 items-center rounded font-semibold underline decoration-coal/30 underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">{{ $item['rotulo'] }}</a>
                    @else
                        <span aria-current="page" class="inline-flex min-h-11 items-center text-muted">{{ $item['rotulo'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
