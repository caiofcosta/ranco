@if ($menu)
    <div wire:poll.15s x-data="{ aberto: false }" x-on:click.outside="aberto = false" x-on:keydown.escape.window="aberto = false"
        class="relative justify-self-end lg:hidden" data-mobile-menu>
        @php($pedidosPendentes = count($contexto['convites'] ?? []) + count($contexto['pedidosDeAmizade'] ?? []))
        <button type="button" x-on:click="aberto = ! aberto" x-bind:aria-expanded="aberto" aria-expanded="false" aria-controls="menu-mobile" aria-label="Menu"
            class="mobile-menu-botao">
            <flux:icon.bars-3 class="size-6" />
            @if ($pedidosPendentes > 0)
                <span class="mobile-nav-badge" aria-hidden="true">{{ $pedidosPendentes > 9 ? '9+' : $pedidosPendentes }}</span>
            @endif
        </button>
        <nav id="menu-mobile" x-show="aberto" x-cloak x-transition.opacity.duration.150ms class="mobile-menu" aria-label="Navegação principal mobile">
            @include('livewire.navegacao.itens-mobile')
        </nav>
    </div>
@else
    <nav wire:poll.15s class="mobile-bottom-nav lg:hidden" aria-label="Navegação principal mobile" data-mobile-nav>
        @include('livewire.navegacao.itens-mobile')
    </nav>
@endif
