{{--
    Navegação da mesa no topo do card, só no celular (md:hidden). No desktop o cabeçalho vive em
    mostrar.blade.php.

    Admin: painel "Preparar mesa" (ação principal, recolhível) com os itens reais do fluxo de
    configuração (Lugares, Pessoas, Jogo, Mesa — cada um abre o fluxo já existente), mais uma linha
    de ações com "Finalizar disputa" (quando há partida concluída) e Ajuda.
    Participante: só Ajuda e Opções (sair da mesa). Nenhum controle administrativo.

    Auto-expandir do painel: uma única vez por mesa (controle em localStorage), no primeiro acesso do
    admin, se a mesa ainda precisa de preparação. Independe do "Não mostrar de novo" da modal. Quando
    a modal inicial aparece, o expandir acontece ao fechá-la (evento orientacao-fechada).
--}}
<div class="md:hidden"
    x-data="{
        preparar: false,
        ehAdmin: @js($tela->ehAdmin),
        mesaPreparada: @js($mesaPreparada),
        chave: 'ranco:preparar-auto:' + @js($tela->id),
        autoExpandir() {
            if (! this.ehAdmin || this.mesaPreparada) return;
            try {
                if (localStorage.getItem(this.chave)) return;
                localStorage.setItem(this.chave, '1');
            } catch (e) {}
            this.preparar = true;
        },
        escolher() { this.preparar = false; },
    }"
    x-init="$nextTick(() => { if (! @js($mostrarOrientacao)) autoExpandir(); })"
    x-on:orientacao-fechada.window="autoExpandir()">

    @if ($tela->ehAdmin)
        {{-- Painel Preparar mesa --}}
        <section class="mesa-preparar" aria-label="Preparar a mesa">
            <button type="button" data-test="nav-preparar"
                x-on:click="preparar = ! preparar" x-bind:aria-expanded="preparar ? 'true' : 'false'" aria-controls="painel-preparar"
                class="mesa-preparar__cabecalho">
                <flux:icon.cog-6-tooth class="size-5 shrink-0" />
                <span class="flex-1 text-left leading-tight">Preparar mesa</span>
                <flux:icon.chevron-down class="size-4 shrink-0 transition-transform" x-bind:class="preparar && 'rotate-180'" />
            </button>

            <div id="painel-preparar" x-show="preparar" x-cloak class="mesa-preparar__painel">
                <p class="mesa-preparar__dica">Organize a mesa antes de começar!</p>
                <ul class="mesa-preparar__itens">
                    <li>
                        <button type="button" wire:click="abrirLugares" x-on:click="escolher()" aria-haspopup="dialog" data-test="preparar-lugares" class="mesa-preparar__item">
                            <flux:icon.user-group class="size-5 shrink-0 text-muted" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold">Lugares</span>
                                <span class="block text-xs text-muted">{{ $quantidadeLugares }} lugares · {{ count($pessoas) }} na mesa</span>
                            </span>
                            <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                        </button>
                    </li>
                    @if ($convites->visivel)
                        <li>
                            <button type="button" x-on:click="escolher(); $refs.convites.showModal()" aria-haspopup="dialog" data-test="preparar-pessoas" class="mesa-preparar__item">
                                <flux:icon.user-plus class="size-5 shrink-0 text-muted" />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold">Pessoas</span>
                                    <span class="block text-xs text-muted">Convidar ou jogar neste aparelho</span>
                                </span>
                                <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                            </button>
                        </li>
                    @endif
                    <li>
                        <button type="button" wire:click="abrirEscolhaDoJogo" x-on:click="escolher()" aria-haspopup="dialog" data-test="preparar-jogo" class="mesa-preparar__item">
                            <flux:icon.puzzle-piece class="size-5 shrink-0 text-muted" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold">Jogo</span>
                                <span class="block text-xs text-muted">
                                    @if ($configuracaoJogo)
                                        {{ \App\Dominio\Jogos\CatalogoDeJogos::nome($configuracaoJogo->jogo ?? 'labirinto') }} · {{ \App\Mesa\PartidaNaMesa::rotuloModo($configuracaoJogo->modo) }}
                                    @else
                                        Nenhum jogo escolhido
                                    @endif
                                </span>
                            </span>
                            <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                        </button>
                    </li>
                    <li>
                        <button type="button" x-on:click="escolher(); $refs.opcoes.showModal()" aria-haspopup="dialog" data-test="preparar-mesa" class="mesa-preparar__item">
                            <flux:icon.adjustments-horizontal class="size-5 shrink-0 text-muted" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold">Mesa</span>
                                <span class="block text-xs text-muted">Renomear{{ $temPartidasConcluidas ? '' : ' ou cancelar' }}</span>
                            </span>
                            <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                        </button>
                    </li>
                </ul>
            </div>
        </section>

        {{-- Ações: Finalizar disputa (só com partida concluída) e Ajuda. --}}
        <div class="mesa-acoes">
            @if ($temPartidasConcluidas)
                <button type="button" x-on:click="$refs.confirmarFinalizar.showModal()" aria-haspopup="dialog" aria-controls="modal-confirmar-finalizar"
                    class="mesa-acoes__finalizar" data-test="nav-finalizar">
                    <flux:icon.flag class="size-5 shrink-0" />
                    <span class="min-w-0 text-left leading-tight">
                        <span class="block font-bold">Finalizar disputa</span>
                        <span class="block text-xs font-normal opacity-80">Encerrar e entregar os troféus</span>
                    </span>
                </button>
            @endif

            <button type="button" x-on:click="$refs.ajuda.showModal()" aria-haspopup="dialog" aria-controls="modal-ajuda"
                class="mesa-acoes__secundario" data-test="nav-ajuda">
                <flux:icon.question-mark-circle class="size-5 shrink-0" />
                <span class="leading-tight">Ajuda</span>
            </button>
        </div>
    @else
        {{-- Participante: sem controles administrativos. --}}
        <div class="mesa-acoes">
            <button type="button" x-on:click="$refs.ajuda.showModal()" aria-haspopup="dialog" aria-controls="modal-ajuda"
                class="mesa-acoes__secundario" data-test="nav-ajuda">
                <flux:icon.question-mark-circle class="size-5 shrink-0" />
                <span class="leading-tight">Ajuda</span>
            </button>
            <button type="button" x-on:click="$refs.opcoes.showModal()" aria-haspopup="dialog"
                class="mesa-acoes__secundario" data-test="nav-opcoes">
                <flux:icon.adjustments-horizontal class="size-5 shrink-0" />
                <span class="leading-tight">Opções</span>
            </button>
        </div>
    @endif
</div>
