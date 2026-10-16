@props(['id', 'tela', 'titulo' => 'Partida', 'compacta' => false])
<x-layouts::app :title="$titulo" :telaCheia="true">
    <div class="mx-auto flex min-w-0 max-w-5xl flex-col gap-6" data-tela-do-jogo>
        <main @class(['order-2 min-w-0', 'hidden' => $tela->partida->pausada && $tela->fase === 'aguardar'])>{{ $slot }}</main>
        <livewire:mesa.painel-do-jogo :id="$id" :numero="$tela->numero" :chave="$tela->chave" :compacta="$compacta" />
    </div>
    @vite('resources/js/mesa/tempo-real.js')
    @vite('resources/js/mesa/tela-do-jogo.js')
</x-layouts::app>
