<div x-data="{}" x-on:click="if ($event.target.closest('a[href=&quot;#convidar&quot;]') && $refs.convites) { $event.preventDefault(); $refs.convites.showModal(); }" x-on:escolher-jogo.window="$refs.jogo?.showModal()" x-on:jogo-confirmado.window="$refs.jogo?.close()" x-on:escolher-lugares.window="$refs.lugares?.showModal()" x-on:lugares-confirmados.window="$refs.lugares?.close()" class="mesa-tela" data-test="mesa-livewire" data-tempo-real-mesa="mesa" data-mesa-id="{{ $id }}">
    @unless ($encerrada)
        {{-- Cabeçalho desktop da mesa --}}
        <header class="hidden md:flex items-center justify-between gap-6 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-[-0.04em] [overflow-wrap:anywhere]" data-test="nome-da-mesa">{{ $tela->nome }}</h1>
                <p class="mt-1 text-sm text-muted" data-test="contexto-da-mesa">{{ $tela->situacaoDaSala }} · {{ count($pessoas) }} na mesa · Administrador: {{ $nomeAdmin }}</p>
            </div>
            <nav class="flex gap-2 flex-shrink-0" aria-label="Ações da mesa">
                <button type="button" x-on:click="$refs.ajuda.showModal()" aria-haspopup="dialog" aria-controls="modal-ajuda"
                    class="inline-flex items-center gap-2 min-h-11 rounded-xl bg-white border border-coal/20 px-4 py-2 text-sm font-bold text-coal transition-colors hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="nav-ajuda">
                    <flux:icon.question-mark-circle class="size-5" />
                    <span class="leading-tight">Ajuda</span>
                </button>
            </nav>
        </header>

        @include('mesa.preparacao')
        @include('mesa.lugares')

        <section class="mesa-area mt-2 rounded-[1.25rem] border border-coal/10 bg-white px-1 pb-2 sm:mt-6 sm:rounded-3xl sm:px-6 sm:py-6" aria-label="Mesa e jogadores">
            <div class="mesa-area__conteudo">
                @include('mesa.navegacao-superior')
                @include('mesa.resumo')
                @include('mesa.modais')
                @include('mesa.configurar')
                @include('mesa.remocao')
            <x-mesa-de-jogo :cadeiras="$cadeiras" :podeRemover="$tela->ehAdmin" data-test="mesa-de-jogo">
                @if ($configuracaoJogo)
                    <p class="mesa-de-jogo__marca">{{ $partida->estado === 'configuracao' ? 'PRÓXIMA PARTIDA' : 'JOGO ATUAL' }}</p>
                    <h2 class="mt-3 text-xl font-black sm:text-3xl">{{ \App\Dominio\Jogos\CatalogoDeJogos::nome($configuracaoJogo->jogo ?? 'labirinto') }}</h2>
                    <p class="mt-2 text-sm">{{ \App\Mesa\PartidaNaMesa::rotuloModo($configuracaoJogo->modo) }}</p>
                    <p class="mt-1 text-xs text-cream/80">Menor tempo por rodada</p>
                    @if ($partida->estado === 'configuracao')
                        <p class="mt-3 text-xs">{{ $partida->podeIniciar ? 'Pronto para começar' : 'Aguardando jogadores' }}</p>
                        @if ($tela->ehAdmin)
                            <form wire:submit="iniciarPartida" method="POST" action="{{ route('mesas.partida.iniciar', $partida->mesaId) }}" class="mt-3">
                                @csrf
                                <button type="submit" data-test="iniciar-partida" @disabled(! $partida->podeIniciar) wire:loading.attr="disabled"
                                    class="mesa-play min-h-11 rounded-xl bg-coral px-3 py-2 text-sm font-black text-coal disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cream">▶ Iniciar partida</button>
                            </form>
                        @else
                            <p class="mt-3 text-xs">O administrador inicia a partida.</p>
                        @endif
                    @elseif ($tela->situacaoDaPartida)
                        <p class="mt-3 text-sm">{{ $tela->situacaoDaPartida }}</p>
                    @endif
                @else
                    <p class="mesa-de-jogo__marca" aria-hidden="true">RANÇO<span>.</span></p>
                    @if ($tela->ehAdmin)
                        <h2 class="mt-3 text-xl font-black sm:text-3xl">Qual vai ser?</h2>
                        <p class="mt-2 text-sm text-cream/85">Escolha o jogo da próxima partida.</p>
                        <button type="button" wire:click="abrirEscolhaDoJogo" class="mt-4 min-h-11 rounded-xl bg-coral px-3 py-2 text-sm font-black text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cream">Escolher jogo</button>
                    @else
                        <h2 class="mt-3 text-xl font-black sm:text-3xl" data-test="aguardando-jogo">Aguardando o próximo jogo</h2>
                        <p class="mt-2 text-sm text-cream/85">O administrador organiza a mesa e escolhe o próximo jogo.</p>
                    @endif
                @endif
            </x-mesa-de-jogo>

            {{-- Painel lateral desktop: cards de configuração (admin) + classificação --}}
            <aside class="hidden lg:flex lg:flex-col gap-2.5" aria-label="Painel da mesa">
                @if ($tela->ehAdmin && $partida->estado !== 'andamento')
                    <div class="mesa-painel-cards">
                        <button type="button" class="mesa-painel-card mesa-painel-card--sand"
                            wire:click="abrirLugares" aria-haspopup="dialog">
                            <p class="mesa-painel-card__rotulo">Lugares</p>
                            <p class="mesa-painel-card__valor">{{ count($pessoas) }}/{{ $quantidadeLugares }}</p>
                            <span class="mesa-painel-card__acao">Ajustar</span>
                        </button>
                        @if ($convites->visivel)
                            <button type="button" class="mesa-painel-card mesa-painel-card--coral"
                                x-on:click="$refs.convites.showModal()" aria-haspopup="dialog">
                                <p class="mesa-painel-card__rotulo">Pessoas</p>
                                <p class="mesa-painel-card__valor">Convidar</p>
                                <span class="mesa-painel-card__acao">Abrir</span>
                            </button>
                        @else
                            <div class="mesa-painel-card mesa-painel-card--sand">
                                <p class="mesa-painel-card__rotulo">Pessoas</p>
                                <p class="mesa-painel-card__valor">Sem vagas</p>
                            </div>
                        @endif
                        <button type="button" class="mesa-painel-card mesa-painel-card--coal mesa-painel-card--wide"
                            wire:click="abrirEscolhaDoJogo" aria-haspopup="dialog">
                            <p class="mesa-painel-card__rotulo">Jogo</p>
                            <p class="mesa-painel-card__valor">
                                @if ($configuracaoJogo)
                                    {{ \App\Dominio\Jogos\CatalogoDeJogos::nome($configuracaoJogo->jogo ?? 'labirinto') }}
                                @else
                                    Nenhum
                                @endif
                            </p>
                            <span class="mesa-painel-card__acao">Escolher</span>
                        </button>
                        <button type="button" class="mesa-painel-card mesa-painel-card--sand mesa-painel-card--wide"
                            x-on:click="$refs.opcoes.showModal()" aria-haspopup="dialog">
                            <p class="mesa-painel-card__rotulo">Mesa</p>
                            <p class="mesa-painel-card__valor">{{ $tela->nome }}</p>
                            <span class="mesa-painel-card__acao">Opções</span>
                        </button>
                    </div>
                @endif
                <section class="mesa-painel-placar" aria-labelledby="painel-placar-titulo">
                    <div class="mesa-painel-placar__cabecalho">
                        <div>
                            <h2 id="painel-placar-titulo" class="mesa-painel-placar__titulo">Classificação</h2>
                            <p class="mesa-painel-placar__auxiliar">Pontos acumulados</p>
                        </div>
                        <button type="button" x-on:click="$refs.resultados.showModal()" aria-haspopup="dialog"
                            class="mesa-painel-placar__historico" data-test="painel-historico">
                            <flux:icon.clock class="size-3.5 shrink-0" />
                            <span>Histórico</span>
                        </button>
                    </div>
                    @if ($classificacao->temResultado)
                        <ol class="mesa-painel-placar__lista">
                            @foreach ($classificacao->linhas as $linha)
                                <li wire:key="painel-placar-{{ $linha['id'] }}"
                                    @class(['mesa-painel-placar__item', 'mesa-painel-placar__item--lider' => $linha['primeiro']])>
                                    <span class="mesa-painel-placar__posicao tabular-nums">{{ $linha['posicao'] }}º</span>
                                    <span class="mesa-painel-placar__nome" title="{{ $linha['nome'] }}">{{ $linha['compacto'] }}</span>
                                    <span class="mesa-painel-placar__pontos tabular-nums">{{ $linha['pontos'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="mesa-painel-placar__vazio">Primeiro jogo decide!</p>
                    @endif
                </section>
            </aside>
            </div>
        </section>

        @include('mesa.escolher-jogo')

        @if ($excedentes > 0)
            <p class="mt-2 text-center text-sm text-muted">+{{ $excedentes }} além dos lugares da mesa</p>
        @endif

        {{--
            Classificação da mesa: no celular ocupa o espaço que era da barra inferior. Dados de
            RankingDaMesa (pontos) + membros ativos; aqui não se pontua nada. No desktop fica a
            modal Resultados, então o rodapé é só do celular (md:hidden).
        --}}
        <footer class="mesa-tela__rodape md:hidden" data-mesa-rodape>
            <section class="mesa-placar" aria-labelledby="titulo-classificacao" data-test="classificacao-da-mesa">
                <div class="mesa-placar__cabecalho">
                    <div class="min-w-0">
                        <h2 id="titulo-classificacao" class="mesa-placar__titulo">Classificação da mesa</h2>
                        <p class="mesa-placar__auxiliar">Pontos acumulados</p>
                    </div>
                    <button type="button" x-on:click="$refs.jogosDaMesa.showModal()" aria-haspopup="dialog" aria-controls="modal-jogos-da-mesa"
                        class="mesa-placar__historico" data-test="abrir-jogos-da-mesa">
                        <flux:icon.clock class="size-4 shrink-0" />
                        <span>Histórico</span>
                    </button>
                </div>

                <ol class="mesa-placar__grade" data-lugares="{{ count($classificacao->linhas) }}">
                    @foreach ($classificacao->linhas as $linha)
                        <li wire:key="classificacao-{{ $linha['id'] }}"
                            @class(['mesa-placar__item', 'mesa-placar__item--lider' => $linha['primeiro']])
                            data-test="linha-da-classificacao">
                            @if ($classificacao->temResultado)
                                <span class="mesa-placar__posicao tabular-nums">{{ $linha['posicao'] }}º</span>
                            @endif
                            <span class="mesa-placar__nome" title="{{ $linha['nome'] }}">{{ $linha['compacto'] }}</span>
                            <span class="mesa-placar__pontos tabular-nums" data-test="pontos-da-classificacao">{{ $linha['pontos'] }}</span>
                        </li>
                    @endforeach
                </ol>

                @unless ($classificacao->temResultado)
                    <p class="mesa-placar__aviso" data-test="classificacao-sem-resultado">A disputa começa depois do primeiro jogo!</p>
                @endunless
            </section>
        </footer>

        {{-- Lista lida pelo tempo real: quem está online agora. As cadeiras acendem a partir dela. --}}
        <ul wire:ignore data-tempo-real-presenca class="sr-only" aria-label="Online na mesa agora"></ul>

        @include('mesa.partida')

        @if ($convites->visivel)
            <dialog x-ref="convites" wire:ignore.self aria-labelledby="titulo-convidar"
                x-init="$nextTick(() => { if (@js((bool) session('status') || $errors->has('convite') || old('origem_amizade') || old('origem_local'))) $el.showModal(); })"
                class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60">
            <section x-data="{ etapa: @js(old('origem_local') ? 'local' : (old('origem_amizade') || str_starts_with((string) session('status'), 'Convite enviado para') ? 'amizade' : 'lista')) }" id="convidar" data-test="convidar-amigos">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="titulo-convidar" class="text-lg font-black tracking-[-0.03em]">Quem vai jogar?</h2>
                    <button type="button" x-on:click="$refs.convites.close()" class="min-h-11 rounded-lg px-3 font-bold focus-visible:outline-2">Fechar</button>
                </div>

                <p class="mt-2 text-sm text-muted">Mesa: {{ $tela->nome }}</p>
                <p class="mt-2 text-sm text-muted">Chame alguém pelo RANÇO ou coloque uma pessoa que está aí com você.</p>
                <div x-show="etapa === 'amizade'" x-cloak>
                    <button type="button" x-on:click="etapa = 'lista'" class="mt-3 min-h-11 font-bold underline">← Voltar aos amigos disponíveis</button>
                    <h3 class="mt-3 font-black">Adicionar amigo</h3>
                    <p class="mt-2 text-sm">Envie um convite de amizade. Depois que a pessoa aceitar, você poderá convidá-la para esta mesa.</p>
                    <form method="POST" action="{{ route('amigos.convidar') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="origem_amizade" value="1">
                        <x-campo nome="email" id="email-amizade" rotulo="E-mail do amigo" tipo="email" :value="old('origem_amizade') ? old('email') : ''" required maxlength="190" />
                        <x-botao>Enviar convite de amizade</x-botao>
                    </form>
                </div>
                {{-- Participante local: joga no aparelho do admin, com a sessão dele. --}}
                <div x-show="etapa === 'local'" x-cloak>
                    <button type="button" x-on:click="etapa = 'lista'" class="mt-3 min-h-11 font-bold underline">← Voltar aos amigos disponíveis</button>
                    <h3 class="mt-3 font-black">Jogar neste aparelho</h3>
                    <p class="mt-2 text-sm">Pra quem vai jogar com você neste mesmo aparelho.</p>
                    <form method="POST" action="{{ route('mesas.participantes-locais', $tela->id) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="origem_local" value="1">
                        <x-campo nome="nome" id="nome-local" rotulo="Nome" :value="old('origem_local') ? old('nome') : ''" required maxlength="80" autocomplete="off" />
                        <x-campo nome="email" id="email-local" rotulo="E-mail" tipo="email" :value="old('origem_local') ? old('email') : ''" required autocomplete="off" />
                        <x-botao data-test="cadastrar-local">Adicionar à mesa</x-botao>
                    </form>
                </div>
                @if (session('status'))
                    <x-aviso class="mt-4">{{ session('status') }}</x-aviso>
                @endif
                @error('convite')
                    <p class="mt-4 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
                @enderror

                <div x-show="etapa === 'lista'">
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" x-on:click="etapa = 'amizade'" class="min-h-11 rounded-xl bg-coral px-5 font-bold">Adicionar amigo</button>
                    <button type="button" x-on:click="etapa = 'local'" data-test="jogar-neste-aparelho" class="min-h-11 rounded-xl border border-coal/20 bg-white px-5 font-bold">Jogar neste aparelho</button>
                </div>
                @if ($convites->pendentes !== [])
                    <section class="mt-5 rounded-2xl border border-coral/30 bg-coral/10 p-4" aria-labelledby="titulo-convites-pendentes" data-test="status-convite-pendente">
                        <h3 id="titulo-convites-pendentes" class="font-black">Convite aguardando resposta</h3>
                        <p class="mt-1 text-sm text-muted">A pessoa pode estar offline ou ainda não ter respondido. Cancele o convite para liberar a vaga e chamar outra pessoa.</p>
                        <ul class="mt-3 grid gap-2">
                            @foreach ($convites->pendentes as $pendente)
                                <li wire:key="convite-status-{{ $pendente['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-coal/10 bg-white px-3 py-3">
                                    <div>
                                        <p class="font-bold">{{ $pendente['nome'] }}</p>
                                        <p class="text-xs text-muted">Convite enviado · aguardando resposta</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <form method="POST" action="{{ route('mesas.convites.cancelar', [$tela->id, $pendente['id']]) }}">
                                            @csrf
                                            <button type="submit" class="min-h-10 rounded-lg bg-coal px-3 text-sm font-bold text-cream" data-test="cancelar-convite-status">Cancelar convite</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($convites->convidaveis === [] && $convites->pendentes === [])
                    <p class=”mt-4 text-sm text-muted”>Nenhum amigo disponível para convidar.</p>

                @else
                    @if ($convites->convidaveis === [])
                        <p class="mt-4 text-sm text-muted">Nenhum amigo disponível para um novo convite. Confira os convites pendentes abaixo.</p>
                    @endif
                    @if ($convites->convidaveis !== [])
                        <div x-show="etapa === 'lista'" x-cloak>
                        <h3 class="mt-5 font-bold">Amigos disponíveis</h3>
                        <ul class="mt-4 grid gap-2">
                            @foreach ($convites->convidaveis as $amigo)
                                <li wire:key="amigo-{{ $amigo['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                                    <span class="font-bold">{{ $amigo['nome'] }}</span>
                                    <form method="POST" action="{{ route('mesas.convites.criar', $tela->id) }}">
                                        @csrf
                                        <input type="hidden" name="amigo" value="{{ $amigo['id'] }}">
                                        <button
                                            type="submit"
                                            data-test="convidar-amigo"
                                            @class([
                                                'inline-flex h-10 items-center rounded-full px-5 text-sm font-black transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal',
                                                'bg-coral text-coal hover:shadow-lg' => $convites->convidarEPrincipal,
                                                'bg-coal text-cream' => ! $convites->convidarEPrincipal,
                                            ])
                                        >Convidar</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                        </div>
                    @endif

                    @if ($convites->pendentes !== [])
                        <h3 class="mt-5 font-bold">Convites pendentes</h3>
                        <ul class="mt-4 grid gap-2">
                            @foreach ($convites->pendentes as $pendente)
                                <li wire:key="convite-{{ $pendente['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                                    <span class="font-bold">{{ $pendente['nome'] }}</span>
                                    <form method="POST" action="{{ route('mesas.convites.cancelar', [$tela->id, $pendente['id']]) }}">
                                        @csrf
                                        <button type="submit" class="rounded text-sm font-semibold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="cancelar-convite">Cancelar</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
                </div>
            </section>
            </dialog>
        @endif

        @include('mesa.opcoes')
    @endunless
</div>
