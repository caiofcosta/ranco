@if ($partida->ehAdmin && $partida->estado !== 'andamento')
    <dialog x-ref="jogo" wire:ignore.self aria-labelledby="titulo-escolher-jogo"
        class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-3xl bg-cream p-5 text-coal shadow-xl backdrop:bg-coal/60 sm:p-8">
        <div x-data="{ jogoSelecionado: null }" x-on:escolher-jogo.window="jogoSelecionado = null">
            <div class="flex items-start justify-between gap-4">
                <h2 id="titulo-escolher-jogo" class="text-2xl font-black" x-text="jogoSelecionado ? 'Configurar jogo' : 'Escolher o jogo'">Escolher o jogo</h2>
                <button type="button" x-on:click="$refs.jogo.close()" class="min-h-11 rounded-lg px-3 font-bold focus-visible:outline-2">Fechar</button>
            </div>
            <div x-show="! jogoSelecionado" data-test="lista-de-jogos">
                <p class="mt-3 text-sm text-muted">Escolha o jogo da vez. Depois você vê as regras e prepara a partida.</p>
                <ul class="mt-5 grid gap-3" x-ref="listaJogos" tabindex="-1" aria-label="Jogos disponíveis">
                    @foreach ($jogosDisponiveis as $jogo)
                        <li>
                            <button type="button"
                                x-on:click="jogoSelecionado = @js($jogo['id']); $nextTick(() => $refs.voltarJogos.focus())"
                                class="flex min-h-24 w-full cursor-pointer items-center gap-4 rounded-2xl border border-coal/15 bg-white p-4 text-left transition-colors hover:border-coral hover:bg-coral/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral">
                                <img src="{{ asset($jogo['logo']) }}" alt="" class="h-14 w-14 shrink-0">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-lg font-black">{{ $jogo['nome'] }}</span>
                                    <span class="mt-1 block text-sm text-muted">{{ $jogo['minimo'] }}–{{ $jogo['maximo'] }} jogadores</span>
                                </span>
                                <span class="text-sm font-bold text-coal">Escolher <span aria-hidden="true">→</span></span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                @if ($jogosDisponiveis === [])
                    <p class="mt-5 rounded-xl border border-coal/10 bg-white p-4 text-sm text-muted">Nenhum jogo está disponível no momento.</p>
                @endif
            </div>
            <div x-show="jogoSelecionado" x-cloak>
                <div class="mt-4 flex items-center gap-3">
                    <button type="button" x-ref="voltarJogos" x-on:click="jogoSelecionado = null; $nextTick(() => $refs.listaJogos.focus())"
                        class="min-h-14 w-28 shrink-0 cursor-pointer rounded-xl border border-coal/20 bg-white px-2 text-xs font-bold sm:w-auto sm:px-4 sm:text-sm hover:bg-coal/10 focus-visible:outline-2 focus-visible:outline-offset-2">← Voltar aos jogos</button>
                    <x-botao x-bind:form="'configurar-' + jogoSelecionado" data-test="criar-partida" class="min-w-0 flex-1">Confirmar jogo</x-botao>
                </div>
                @foreach ($jogosDisponiveis as $jogo)
                    <div x-show="jogoSelecionado === @js($jogo['id'])" data-test="configuracao-{{ $jogo['id'] }}">
                        @include('mesa.jogos.'.$jogo['id'])
                    </div>
                @endforeach
            </div>
        </div>
    </dialog>
@endif
