{{--
    Modais de Resultados (Ranking e Histórico), Ajuda, Resultado da partida, Orientação de primeiro
    acesso e Confirmações de encerramento, abertas pela navegação da mesa.
    O x-data das abas fica dentro da <dialog>: nela mesma, ele tiraria o x-ref do alcance do menu.
--}}

<dialog x-ref="resultados" id="modal-resultados" wire:ignore.self aria-labelledby="titulo-resultados"
    class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60"
    data-test="modal-resultados">
    <div x-data="{ aba: 'ranking' }">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 id="titulo-resultados" class="text-xl font-black">Resultados</h2>
            <button type="button" x-on:click="$refs.resultados.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10 focus-visible:outline-2">Fechar</button>
        </div>

        <div class="mb-4 flex gap-4 border-b border-coal/10" role="tablist" aria-label="Resultados da mesa">
            <button type="button" role="tab" id="aba-ranking" aria-controls="painel-ranking"
                x-on:click="aba = 'ranking'" x-bind:aria-selected="aba === 'ranking'"
                x-bind:class="aba === 'ranking' ? 'border-coal text-coal font-black' : 'border-transparent text-muted font-bold'"
                class="-mb-px min-h-11 border-b-2 text-sm transition focus-visible:outline-2" data-test="aba-ranking">Ranking</button>
            <button type="button" role="tab" id="aba-historico" aria-controls="painel-historico"
                x-on:click="aba = 'historico'" x-bind:aria-selected="aba === 'historico'"
                x-bind:class="aba === 'historico' ? 'border-coal text-coal font-black' : 'border-transparent text-muted font-bold'"
                class="-mb-px min-h-11 border-b-2 text-sm transition focus-visible:outline-2" data-test="aba-historico">Histórico</button>
        </div>

        <div x-show="aba === 'ranking'" role="tabpanel" id="painel-ranking" aria-labelledby="aba-ranking" class="text-sm">
            @if ($ranking->linhas !== [])
                <ol class="divide-y divide-coal/10" data-test="ranking-da-mesa">
                    @php
                        $posicao = 0;
                        $pontosAnteriores = null;
                    @endphp
                    @foreach ($ranking->linhas as $linha)
                        @php
                            if ($linha['pontos'] !== $pontosAnteriores) {
                                $posicao++;
                                $pontosAnteriores = $linha['pontos'];
                            }
                        @endphp
                        <li wire:key="ranking-modal-{{ $linha['id'] }}" class="flex min-w-0 items-baseline gap-3 py-2.5" data-test="linha-do-ranking">
                            <span class="w-7 shrink-0 font-black tabular-nums">{{ $posicao }}º</span>
                            <span class="min-w-0 flex-1 font-bold [overflow-wrap:anywhere]">{{ $linha['nome'] }}</span>
                            <span class="shrink-0 tabular-nums"><strong data-test="pontos-do-ranking">{{ $linha['pontos'] }}</strong> {{ $linha['pontos'] === 1 ? 'ponto' : 'pontos' }}</span>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="text-muted" data-test="ranking-vazio">Ainda não tem resultado por aqui.<br>Joguem uma partida e depois voltem pra ver como ficou.</p>
            @endif
        </div>

        <div x-show="aba === 'historico'" x-cloak role="tabpanel" id="painel-historico" aria-labelledby="aba-historico" class="text-sm">
            @if ($historico->partidas !== [])
                @include('mesa.historico-lista', ['chave' => 'historico'])
            @else
                <p class="text-muted" data-test="historico-vazio">Ainda não tem nenhuma partida por aqui.</p>
            @endif
        </div>
    </div>
</dialog>

<dialog x-ref="ajuda" id="modal-ajuda" wire:ignore.self aria-labelledby="titulo-ajuda"
    class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 id="titulo-ajuda" class="text-xl font-black">Como funciona a mesa</h2>
        <button type="button" x-on:click="$refs.ajuda.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10 focus-visible:outline-2">Fechar</button>
    </div>

    <div class="space-y-3 text-sm leading-relaxed text-coal" data-test="ajuda-conteudo">
        @if ($tela->ehAdmin)
            <p>Você organiza a brincadeira por aqui.</p>
            <p>Toque em <strong>Preparar mesa</strong> para escolher quantas pessoas vão jogar, organizar os lugares e escolher o jogo.</p>
            <p>Cada partida soma pontos. Quando quiser terminar, toque em <strong>Finalizar disputa</strong> e veja quem levou os troféus.</p>
            <p>Depois de jogar, o ranking e o histórico das partidas ficam aqui embaixo.</p>
        @else
            <p>Você está numa mesa com a galera.</p>
            <p>Quem administra organiza os participantes e escolhe o próximo jogo. Quando estiver tudo pronto, você entra na partida.</p>
            <p>Cada jogo vale pontos e soma na classificação da mesa. No final, a gente descobre quem levou os troféus.</p>
            <p>O ranking e o histórico das partidas ficam aqui embaixo.</p>
        @endif
    </div>
</dialog>

{{--
    Resultado da partida que acabou de terminar: abre na chegada à mesa e só fecha pelo jogador.
    A partida vem fixa do mount, então o redesenho pelo tempo real (inclusive a próxima partida
    sendo preparada) não troca nem remove a modal; o wire:ignore.self preserva o atributo open.
    Celular (abaixo de md): cabeçalho e botão fixos, classificação e rodadas rolam no meio
    (resources/css/resultado-da-partida.css). Desktop: a apresentação simples, inalterada.
--}}
@if ($resultadoDaPartida)
    @php($fimDaPartida = $resultadoDaPartida->partida)
    <dialog x-ref="resultadoPartida" id="modal-resultado-partida" wire:key="resultado-partida-{{ $resultado }}" wire:ignore.self aria-labelledby="titulo-resultado-partida"
        x-init="$nextTick(() => $el.showModal())"
        x-on:close="
            const url = new URL(location.href);
            url.searchParams.delete('resultado');
            history.replaceState(history.state, '', url);
            $wire.dispensarResultado();
        "
        class="resultado-partida m-auto w-[calc(100%-2rem)] rounded-3xl bg-cream text-coal shadow-xl backdrop:bg-coal/60 md:max-h-[85dvh] md:max-w-sm md:overflow-y-auto md:p-6"
        data-test="modal-resultado-partida">
        <div class="resultado-partida__celular md:hidden" data-test="resultado-celular">
            <header class="shrink-0 px-4 pt-5 pb-3 text-center">
                <p class="flex items-center justify-center gap-1.5 text-xs font-bold text-muted" data-test="jogo-do-resultado">
                    @if ($resultadoDaPartida->logo)
                        <img src="{{ asset($resultadoDaPartida->logo) }}" alt="" class="size-4 shrink-0 object-contain">
                    @endif
                    <span>{{ $resultadoDaPartida->jogo }} · Partida {{ $resultadoDaPartida->numero }}</span>
                </p>
                <h2 class="mt-1 text-2xl font-black tracking-[-0.03em]">Fim de jogo!</h2>
                <p class="mt-2 flex items-center justify-center gap-2 text-lg font-black leading-tight [overflow-wrap:anywhere]" data-test="vencedor-do-resultado-celular">
                    @if ($fimDaPartida->vencedorNome)
                        <flux:icon.trophy variant="solid" class="size-5 shrink-0 text-coral" />
                    @endif
                    <span>{{ $fimDaPartida->vencedorNome ? $fimDaPartida->vencedorNome.($fimDaPartida->variosVencedores ? ' venceram.' : ' venceu.') : 'Partida encerrada sem vencedor.' }}</span>
                </p>
                @if ($fimDaPartida->fraseDaMedalha)
                    {{-- Até duas linhas; "ver mais" só aparece quando o texto foi cortado. --}}
                    <div x-data="{ inteira: false, cortada: false }"
                        x-init="$nextTick(() => cortada = $refs.provocacao.scrollHeight > $refs.provocacao.clientHeight + 1)"
                        class="mt-1">
                        <p x-ref="provocacao" x-bind:class="{ 'line-clamp-2': ! inteira }" class="line-clamp-2 text-sm italic leading-snug text-coal/75 [overflow-wrap:anywhere]" data-test="provocacao-do-resultado">{{ $fimDaPartida->fraseDaMedalha }}</p>
                        <button type="button" x-show="cortada" x-cloak x-on:click="inteira = ! inteira" x-bind:aria-expanded="inteira" x-text="inteira ? 'ver menos' : 'ver mais'"
                            class="-my-2 inline-flex min-h-11 items-center px-3 text-xs font-bold text-muted underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-coal">ver mais</button>
                    </div>
                @endif
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain border-t border-coal/10 px-4 py-3" data-test="resultado-celular-conteudo">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <h3 class="whitespace-nowrap text-sm font-black">Resultado da partida</h3>
                    <span class="ml-auto whitespace-nowrap text-xs text-muted">Pontos recebidos</span>
                </div>
                <ol class="mt-1 divide-y divide-coal/10" data-test="classificacao-do-resultado">
                    @foreach ($resultadoDaPartida->classificacao as $linha)
                        <li wire:key="resultado-celular-{{ $resultado }}-{{ $linha['id'] }}" class="flex min-h-11 items-center gap-2 py-1">
                            <span class="flex w-6 shrink-0 justify-center">
                                @if ($linha['medalha'])
                                    <img src="{{ asset($linha['medalha']['arquivo']) }}" alt="{{ $linha['medalha']['descricao'] }}" class="h-8 w-auto" decoding="async">
                                @endif
                            </span>
                            <span class="w-7 shrink-0 text-sm font-bold tabular-nums text-muted">{{ $linha['colocacao'] !== null ? $linha['colocacao'].'º' : '—' }}</span>
                            <span class="min-w-0 flex-1 font-bold [overflow-wrap:anywhere]">
                                {{ $linha['nome'] }}
                                @if ($linha['empate'])
                                    <span class="text-xs font-semibold text-muted">· empate</span>
                                @endif
                            </span>
                            <span class="shrink-0 font-black tabular-nums">{{ $linha['pontos'] > 0 ? '+'.$linha['pontos'].' '.($linha['pontos'] === 1 ? 'pt' : 'pts') : '0 pts' }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($resultadoDaPartida->rodadas !== [])
                    <div x-data="{ rodadas: false }" class="mt-2 border-t border-coal/10 pt-1">
                        <button type="button" x-on:click="rodadas = ! rodadas" x-bind:aria-expanded="rodadas" aria-controls="rodadas-da-partida-{{ $resultado }}" data-test="ver-rodadas"
                            class="flex min-h-11 w-full items-center justify-between gap-2 rounded-lg text-sm font-bold focus-visible:outline-2 focus-visible:outline-coal">
                            <span x-text="rodadas ? 'Recolher rodadas' : 'Ver rodadas'">Ver rodadas</span>
                            <span class="transition-transform" x-bind:class="{ 'rotate-180': rodadas }"><flux:icon.chevron-down variant="mini" class="size-5" /></span>
                        </button>
                        <div id="rodadas-da-partida-{{ $resultado }}" x-show="rodadas" x-cloak class="space-y-3 pb-1" data-test="rodadas-do-resultado">
                            @foreach ($resultadoDaPartida->rodadas as $rodada)
                                <section wire:key="rodada-{{ $resultado }}-{{ $rodada['numero'] }}" aria-labelledby="rodada-{{ $resultado }}-{{ $rodada['numero'] }}">
                                    <h4 id="rodada-{{ $resultado }}-{{ $rodada['numero'] }}" class="text-xs font-black uppercase tracking-wide text-muted">Rodada {{ $rodada['numero'] }}</h4>
                                    <ol class="mt-1 divide-y divide-coal/10 text-sm">
                                        @foreach ($rodada['linhas'] as $linha)
                                            <li wire:key="rodada-{{ $resultado }}-{{ $rodada['numero'] }}-{{ $linha['id'] }}" class="flex items-center gap-2 py-1.5">
                                                <span class="w-7 shrink-0 tabular-nums text-muted">{{ $linha['posicao'] !== null ? $linha['posicao'].'º' : '—' }}</span>
                                                <span class="min-w-0 flex-1 [overflow-wrap:anywhere]">
                                                    <span class="font-bold">{{ $linha['nome'] }}</span>
                                                    <span class="block text-xs text-muted">{{ $linha['resultado'] }}</span>
                                                </span>
                                                <span class="shrink-0 font-bold tabular-nums">{{ $linha['pontos'] > 0 ? '+'.$linha['pontos'] : '0' }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                </section>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <footer class="shrink-0 border-t border-coal/10 px-4 pt-3 pb-5">
                <button type="button" x-on:click="$refs.resultadoPartida.close()" data-test="continuar-na-mesa-celular"
                    class="min-h-11 w-full rounded-xl bg-coral px-4 font-black hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                    Continuar na mesa
                </button>
            </footer>
        </div>

        <div class="hidden md:block">
            <h2 id="titulo-resultado-partida" class="text-xl font-black">Fim da partida</h2>
            <p class="mt-3 font-bold" data-test="vencedor-do-resultado">
                {{ $fimDaPartida->vencedorNome ? $fimDaPartida->vencedorNome.($fimDaPartida->variosVencedores ? ' venceram.' : ' venceu.') : 'Partida encerrada sem vencedor.' }}
            </p>
            <h3 class="mt-5 text-sm font-black">Resultado final</h3>
            <ol class="mt-2 divide-y divide-coal/10">
                @foreach ($fimDaPartida->placar as $linha)
                    <li wire:key="resultado-partida-{{ $resultado }}-{{ $linha['id'] }}" class="flex items-center justify-between gap-4 py-2">
                        <span class="break-words font-bold">{{ $linha['nome'] }}</span>
                        <span class="shrink-0 tabular-nums">{{ $linha['pontos'] }} pontos</span>
                    </li>
                @endforeach
            </ol>
            @if ($fimDaPartida->fraseDaMedalha)
                <x-voz-do-ranco class="mt-5">{{ $fimDaPartida->fraseDaMedalha }}</x-voz-do-ranco>
            @endif
            <button type="button" x-on:click="$refs.resultadoPartida.close()" data-test="continuar-na-mesa"
                class="mt-6 min-h-11 w-full rounded-xl bg-coral px-4 font-black hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                Continuar na mesa
            </button>
        </div>
    </dialog>
@endif

{{-- Orientação de primeiro acesso, por papel: abre automaticamente na primeira entrada do usuário. --}}
@if ($mostrarOrientacao)
    {{--
        Abre uma vez por visita da mesa (sessionStorage): não repopa nos recarregamentos da mesma
        sessão — por exemplo ao adicionar um jogador no mesmo aparelho (POST + redirect) — mas volta
        a aparecer numa nova visita. O "Não mostrar de novo" (servidor) silencia de vez; a orientação
        segue acessível pela Ajuda.
    --}}
    <dialog x-ref="orientacao" id="modal-orientacao" wire:ignore.self aria-labelledby="titulo-orientacao"
        x-init="$nextTick(() => {
            try {
                const chave = 'ranco:orientacao-vista:' + @js($tela->id);
                if (sessionStorage.getItem(chave)) return;
                sessionStorage.setItem(chave, '1');
            } catch (e) {}
            $el.showModal();
        })"
        x-on:close="$dispatch('orientacao-fechada')"
        class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-sm overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60"
        data-test="modal-orientacao">
        <div x-data="{ naoMostrar: false }">
            @if ($tela->ehAdmin)
                <h2 id="titulo-orientacao" class="text-xl font-black">Bora preparar a mesa?</h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed">
                    <p>Você organiza a brincadeira por aqui!</p>
                    <p>Toque em <strong>Preparar mesa</strong> para escolher quantas pessoas vão jogar, organizar os lugares e escolher o primeiro jogo.</p>
                    <p>Cada partida soma pontos. Quando quiser terminar a disputa, é só finalizar e ver quem levou os troféus!</p>
                </div>
            @else
                <h2 id="titulo-orientacao" class="text-xl font-black">Você entrou na mesa!</h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed">
                    <p>Agora é esperar a galera!</p>
                    <p>Quem administra a mesa organiza os participantes e escolhe o próximo jogo. Quando estiver tudo pronto, você poderá entrar na partida.</p>
                    <p>Cada jogo vale pontos, e tudo vai somando na classificação da mesa. No final, a gente descobre quem levou os troféus!</p>
                </div>
            @endif

            <label class="mt-5 flex items-center gap-2 text-sm">
                <input type="checkbox" x-model="naoMostrar" class="size-4 rounded border-coal/30 accent-coral" data-test="orientacao-nao-mostrar">
                <span>Não mostrar de novo</span>
            </label>

            <button type="button" data-test="orientacao-entendi"
                x-on:click="$wire.dispensarOrientacao(naoMostrar); $refs.orientacao.close()"
                class="mt-4 min-h-11 w-full rounded-xl bg-coral px-4 font-black hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                Entendi!
            </button>
        </div>
    </dialog>
@endif

{{-- Confirmação de finalização (com partida concluída). O cancelamento vive dentro de "Opções". --}}
@if ($tela->ehAdmin)
    <dialog x-ref="confirmarFinalizar" id="modal-confirmar-finalizar" wire:ignore.self aria-labelledby="titulo-confirmar-finalizar"
        class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-sm overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60"
        data-test="modal-confirmar-finalizar">
        <h2 id="titulo-confirmar-finalizar" class="text-xl font-black">Finalizar a disputa?</h2>
        <p class="mt-3 text-sm leading-relaxed">A disputa termina aqui! Vamos mostrar a classificação final e descobrir quem levou os troféus.</p>
        <div class="mt-5 flex gap-3">
            <button type="button" x-on:click="$refs.confirmarFinalizar.close()"
                class="min-h-11 flex-1 rounded-xl border border-coal/20 bg-white px-4 font-bold hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                Continuar jogando
            </button>
            <form method="POST" action="{{ route('mesas.encerrar', $tela->id) }}" class="flex-1">
                @csrf
                <button type="submit" data-test="confirmar-finalizar-mesa"
                    class="min-h-11 w-full rounded-xl bg-coral px-4 font-black focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                    Finalizar disputa
                </button>
            </form>
        </div>
    </dialog>
@endif

{{-- Histórico aberto pela área da classificação, no rodapé do celular. Mesmos dados da aba Histórico. --}}
<dialog x-ref="jogosDaMesa" id="modal-jogos-da-mesa" wire:ignore.self aria-labelledby="titulo-jogos-da-mesa"
    class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60 md:hidden"
    data-test="modal-jogos-da-mesa">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="titulo-jogos-da-mesa" class="text-xl font-black">Jogos da mesa</h2>
        <button type="button" x-on:click="$refs.jogosDaMesa.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10 focus-visible:outline-2">Fechar</button>
    </div>
    <div class="text-sm">
        @if ($historico->partidas !== [])
            @include('mesa.historico-lista', ['chave' => 'jogos-da-mesa'])
        @else
            <p class="text-muted" data-test="jogos-da-mesa-vazio">Nenhum jogo disputado ainda.</p>
        @endif
    </div>
</dialog>
