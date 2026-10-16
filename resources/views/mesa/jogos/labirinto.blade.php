@php
    $abasDoJogo = ['labirinto' => 'Labirinto', 'modo' => 'Modo', 'pontuacao' => 'Pontuação', 'metas' => 'Metas'];
    $modoMetaInicial = $this->meta_pontos !== null ? 'pontos' : ($this->limite_rodadas !== null ? 'rodadas' : 'nenhum');
@endphp
<form id="configurar-labirinto" x-data="configuracaoDoJogo('labirinto', @js($modoMetaInicial))"
    x-on:escolher-jogo.window="reiniciar()"
    x-on:keydown="impedirNumeroNegativo($event)"
    x-on:input="corrigirNumeroNegativo($event)"
    x-on:change="corrigirNumeroNegativo($event)"
    x-on:invalid.capture="mostrarCampo($event.target.name); $nextTick(() => $event.target.focus())"
    x-effect="mostrarErros(@js(array_keys($errors->messages())))"
    wire:submit="{{ $configuracaoJogo ? "salvarConfiguracao('labirinto')" : "criarPartida('labirinto')" }}" method="POST"
    action="{{ route('mesas.partidas.criar', $partida->mesaId) }}" class="mt-4">
    @csrf
    <input type="hidden" name="jogo" value="labirinto">
    @error('partida')
        <p class="mb-3 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror
    <div role="tablist" aria-label="Configuração do Labirinto" class="grid grid-cols-4 gap-1 border-b border-coal/15">
        @foreach ($abasDoJogo as $id => $rotulo)
            <button type="button" id="aba-jogo-{{ $id }}" role="tab" aria-controls="painel-jogo-{{ $id }}"
                x-bind:aria-selected="aba === '{{ $id }}'" x-bind:tabindex="aba === '{{ $id }}' ? 0 : -1"
                x-on:click="selecionar('{{ $id }}')"
                x-on:keydown="navegar($event, '{{ $id }}', $el.parentElement)"
                x-bind:class="aba === '{{ $id }}' ? 'border-coral text-coal' : 'border-transparent text-muted hover:text-coal'"
                class="min-h-11 border-b-2 px-1 text-xs font-bold focus-visible:outline-2 focus-visible:outline-offset-2 sm:text-sm">{{ $rotulo }}</button>
        @endforeach
    </div>
    <section id="painel-jogo-labirinto" role="tabpanel" aria-labelledby="aba-jogo-labirinto" tabindex="0"
        x-show="aba === 'labirinto'" x-cloak class="mt-4 space-y-4 focus-visible:outline-2">
        <section class="mt-4" aria-label="Labirinto">
            <h3 class="text-xl font-black">Labirinto</h3>
            <p class="mt-1 text-sm">Leve a bolinha da largada até a saída no menor tempo possível.</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                <span class="rounded-full bg-white px-3 py-1.5"><span aria-hidden="true">👥</span> {{ $padraoJogo->minimoJogadores }}–{{ $padraoJogo->maximoJogadores }} jogadores</span>
                <span class="rounded-full bg-coral/10 px-3 py-1.5"><span aria-hidden="true">🏁</span> Vence o menor tempo na rodada</span>
            </div>
        </section>
        <x-como-jogar-labirinto />
        <section class="mt-5" aria-label="Como funciona a disputa">
            <h3 class="font-black">Como funciona</h3>
            <p class="mt-2 text-sm text-muted">Todos percorrem o mesmo labirinto na rodada. O tempo de cada tentativa é registrado e os resultados são comparados ao final.</p>
            <p class="mt-2 text-sm text-muted">Cada colocação rende os pontos definidos abaixo. Empatados recebem os mesmos pontos, sem pular a colocação seguinte. Quem acumular mais pontos vence a partida.</p>
        </section>
    </section>
    <section id="painel-jogo-modo" role="tabpanel" aria-labelledby="aba-jogo-modo" tabindex="0"
        x-show="aba === 'modo'" x-cloak class="mt-4 space-y-4 focus-visible:outline-2">
            @if (count($partida->modos) > 1)
                <fieldset data-test="modo-da-partida">
                    <legend class="text-sm font-bold text-coal">Modo</legend>
                    <div class="mt-2 grid gap-2">
                        @foreach ($partida->modos as $modo)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3 text-sm font-bold has-[:checked]:border-coal has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-coal">
                                <input
                                    type="radio"
                                    name="modo"
                                    value="{{ $modo['valor'] }}"
                                    wire:model="modo"
                                    class="accent-coal"
                                    @checked($this->modo === $modo['valor'])
                                >
                                <span>
                                    <span class="block">{{ $modo['rotulo'] }}</span>
                                    <span class="mt-1 block text-xs font-normal leading-relaxed text-muted">
                                        @if ($modo['valor'] === \App\Dominio\Partida\ModoExecucao::Sequencial->value)
                                            Cada jogador faz sua tentativa separadamente. Quando termina, começa a vez do próximo.
                                        @else
                                            Todos jogam na mesma rodada e os resultados são comparados no final. Quem está jogando neste aparelho joga um por vez.
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('modo')
                        <p class="mt-2 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>
            @endif
            <div>
                <div class="flex items-end gap-3">
                    <div class="min-w-0 flex-1">
                        <x-campo wire:model="prazo_vez_segundos" nome="prazo_vez_segundos" rotulo="Prazo da vez" tipo="number" min="1" inputmode="numeric" data-numero-nao-negativo aria-describedby="ajuda-prazo" placeholder="{{ $partida->prazoPadraoSegundos }}" />
                    </div>
                    <span class="pb-4 text-sm text-muted">segundos</span>
                </div>
                <p id="ajuda-prazo" class="mt-2 text-xs text-muted">Prazo para iniciar sua vez. Após clicar, há 3 segundos de preparação e um novo prazo completo para concluir a jogada. Padrão: {{ $partida->prazoPadraoSegundos }} segundos.</p>
            </div>
    </section>
    <section id="painel-jogo-pontuacao" role="tabpanel" aria-labelledby="aba-jogo-pontuacao" tabindex="0"
        x-show="aba === 'pontuacao'" x-cloak class="mt-4 space-y-4 focus-visible:outline-2">
            <fieldset>
                <legend class="text-sm font-bold">Pontos por colocação</legend>
                <input type="hidden" name="personalizar_pontos" value="1">
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach (['1º lugar', '2º lugar', '3º lugar', 'Demais posições'] as $indice => $rotulo)
                            <div wire:key="pontos-posicao-{{ $indice }}">
                                <label for="pontos-posicao-{{ $indice }}" class="block text-sm font-bold">{{ $rotulo }}</label>
                                <input id="pontos-posicao-{{ $indice }}" type="number" name="pontos_por_posicao[{{ $indice }}]" wire:model="pontos_por_posicao.{{ $indice }}" min="0" max="2147483647" step="1" inputmode="numeric" data-numero-nao-negativo
                                    class="mt-2 min-h-11 w-full rounded-xl border border-coal/20 bg-white px-3">
                                @error('pontos_por_posicao.'.$indice)<p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-muted">"Demais posições" vale do 4º lugar em diante. A pontuação desta partida não pode ser alterada depois que ela começar.</p>
                    @error('pontos_por_posicao')<p role="alert" class="mt-2 text-xs text-danger">{{ $message }}</p>@enderror
            </fieldset>
    </section>
    <section id="painel-jogo-metas" role="tabpanel" aria-labelledby="aba-jogo-metas" tabindex="0"
        x-show="aba === 'metas'" x-cloak class="mt-4 space-y-4 focus-visible:outline-2">
            <fieldset>
                <legend class="text-sm font-bold">Como a partida termina?</legend>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach (['pontos' => 'Por pontos', 'rodadas' => 'Por rodadas', 'nenhum' => 'Sem limite'] as $valor => $rotulo)
                        <label class="flex cursor-pointer flex-col gap-1 rounded-2xl border border-coal/10 bg-white px-3 py-3 text-sm font-bold has-[:checked]:border-coal has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-coal">
                            <input type="radio" x-model="modoMeta" value="{{ $valor }}" x-on:change="aoMudarModoMeta($el)" class="sr-only">
                            <span>{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div x-show="modoMeta === 'pontos'" x-cloak>
                <x-campo wire:model="meta_pontos" nome="meta_pontos" rotulo="Meta de pontos" tipo="number" min="1" step="1" inputmode="numeric" data-numero-nao-negativo x-bind:required="modoMeta === 'pontos'" :value="$this->meta_pontos" placeholder="Ex: 10" />
                <p class="mt-2 text-xs text-muted">A partida termina quando alguém atingir essa pontuação.</p>
            </div>
            <div x-show="modoMeta === 'rodadas'" x-cloak>
                <x-campo wire:model="limite_rodadas" nome="limite_rodadas" rotulo="Limite de rodadas" tipo="number" min="1" step="1" inputmode="numeric" data-numero-nao-negativo x-bind:required="modoMeta === 'rodadas'" :value="$this->limite_rodadas" placeholder="Ex: 5" />
                <p class="mt-2 text-xs text-muted">A partida termina ao completar esse número de rodadas.</p>
            </div>
    </section>
</form>
