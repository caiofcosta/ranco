{{-- Miniatura do mapa do Labirinto, gerada da grade (# parede, I início, C chegada). Decorativa. --}}
@props(['linhas'])

@php
    $tamanho = count($linhas);
    $paredes = [];
    $inicio = null;
    $chegada = null;
    foreach ($linhas as $y => $linha) {
        // Paredes vizinhas na mesma linha viram um retângulo só.
        preg_match_all('/#+/', $linha, $trechos, PREG_OFFSET_CAPTURE);
        foreach ($trechos[0] as [$trecho, $x]) {
            $paredes[] = [$x, $y, strlen($trecho)];
        }
        if (($x = strpos($linha, 'I')) !== false) {
            $inicio = [$x, $y];
        }
        if (($x = strpos($linha, 'C')) !== false) {
            $chegada = [$x, $y];
        }
    }
@endphp

<svg viewBox="0 0 {{ $tamanho }} {{ $tamanho }}" aria-hidden="true" focusable="false" shape-rendering="crispEdges" {{ $attributes->class('block') }}>
    <rect width="{{ $tamanho }}" height="{{ $tamanho }}" class="fill-cream" />
    @foreach ($paredes as [$x, $y, $largura])
        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $largura }}" height="1" class="fill-coal" />
    @endforeach
    @if ($inicio)
        <circle cx="{{ $inicio[0] + .5 }}" cy="{{ $inicio[1] + .5 }}" r=".34" class="fill-coral" shape-rendering="geometricPrecision" />
    @endif
    @if ($chegada)
        <rect x="{{ $chegada[0] + .18 }}" y="{{ $chegada[1] + .18 }}" width=".64" height=".64" class="fill-coral" />
    @endif
</svg>
