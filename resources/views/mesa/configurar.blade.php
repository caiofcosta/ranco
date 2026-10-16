{{--
    Configurar: o que o admin resolve antes da próxima partida. Lista as pendências da regra de
    início e abre os fluxos que já existem: lugares, convite e escolha/configuração do jogo.
    Sem x-data na <dialog>, para o x-ref continuar ao alcance do menu.
--}}
@if ($tela->ehAdmin)
    <dialog x-ref="configurar" id="modal-configurar" wire:ignore.self aria-labelledby="titulo-configurar"
        class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60"
        data-test="modal-configurar">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h2 id="titulo-configurar" class="text-xl font-black">Configurar</h2>
            <button type="button" x-on:click="$refs.configurar.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10 focus-visible:outline-2">Fechar</button>
        </div>

        @if ($tela->pendencias !== [])
            <section class="rounded-2xl border border-coral/40 bg-coral/10 p-4" aria-labelledby="titulo-pendencias" data-test="pendencias-da-mesa">
                <h3 id="titulo-pendencias" class="text-sm font-black">Falta resolver</h3>
                <ul class="mt-2 grid list-disc gap-1 pl-5 text-sm">
                    @foreach ($tela->pendencias as $pendencia)
                        <li data-test="pendencia">{{ $pendencia }}</li>
                    @endforeach
                </ul>
            </section>
        @else
            <p class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-900" data-test="sem-pendencias">Tudo pronto. Quando quiser, pode começar.</p>
        @endif

        <ul class="mt-5 divide-y divide-coal/10 overflow-hidden rounded-2xl border border-coal/10 bg-white">
            <li>
                <button type="button" wire:click="abrirLugares" aria-haspopup="dialog" data-test="configurar-lugares"
                    class="flex min-h-14 w-full cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-coal/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-coal">
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold">Lugares</span>
                        <span class="block text-sm text-muted">{{ $quantidadeLugares }} lugares · {{ count($pessoas) }} na mesa</span>
                    </span>
                    <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                </button>
            </li>
            @if ($convites->visivel)
                <li>
                    <button type="button" x-on:click="$refs.convites.showModal()" aria-haspopup="dialog" data-test="configurar-jogadores"
                        class="flex min-h-14 w-full cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-coal/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-coal">
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold">Pessoas</span>
                            <span class="block text-sm text-muted">Convidar ou jogar neste aparelho</span>
                        </span>
                        <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                    </button>
                </li>
            @endif
            <li>
                <button type="button" wire:click="abrirEscolhaDoJogo" aria-haspopup="dialog" data-test="configurar-jogo"
                    class="flex min-h-14 w-full cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-coal/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-coal">
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold">Jogo</span>
                        <span class="block text-sm text-muted">
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
                <button type="button" x-on:click="$refs.opcoes.showModal()" aria-haspopup="dialog" data-test="configurar-mesa"
                    class="flex min-h-14 w-full cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-coal/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-coal">
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold">Mesa</span>
                        <span class="block text-sm text-muted">Renomear ou encerrar</span>
                    </span>
                    <flux:icon.chevron-right class="size-4 shrink-0 text-muted" />
                </button>
            </li>
        </ul>
    </dialog>
@endif
