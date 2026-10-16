@php
    $convites = count($contexto['convites'] ?? []);
    $amizades = count($contexto['pedidosDeAmizade'] ?? []);
    $pedidosPendentes = $convites + $amizades;
    $itens = [
        ['inicio', 'Início', route('mobile.inicio'), 'home'],
        ['pedidos', 'Pedidos', route('mobile.pedidos'), 'inbox'],
        ['jogar-juntos', 'Jogar juntos', route('mobile.jogar-juntos'), 'users'],
        ['jogar-solo', 'Jogar solo', route('jogar.index'), 'play'],
        ['mais', 'Mais', route('mobile.mais'), 'ellipsis'],
    ];
    $icones = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
        'ellipsis' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    ];
@endphp
@foreach ($itens as [$id, $rotulo, $url, $icone])
    <a href="{{ $url }}" wire:navigate data-mobile-destino="{{ $id }}"
        @if ($ativa === $id) aria-current="page" @endif
        class="mobile-nav-item">
        <span class="relative grid size-7 place-items-center">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icones[$icone] !!}</svg>
            @if ($id === 'pedidos' && $pedidosPendentes > 0)
                <span class="mobile-nav-badge" aria-label="{{ $pedidosPendentes }} {{ $pedidosPendentes === 1 ? 'pedido' : 'pedidos' }}">{{ $pedidosPendentes > 9 ? '9+' : $pedidosPendentes }}</span>
            @endif
        </span>
        <span class="mobile-nav-rotulo">{{ $rotulo }}</span>
    </a>
@endforeach
