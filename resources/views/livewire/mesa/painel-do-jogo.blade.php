<div class="contents" data-tempo-real-mesa="jogo" data-mesa-id="{{ $id }}" wire:poll.30s="atualizarMesa">
    @if ($tela)
        @php($partida = $tela->partida)
        @if ($tela->fase !== 'fim')
            <header @class(['order-1 w-full min-w-0 space-y-3', 'hidden' => $compacta && $tela->fase === 'jogar']) data-prazo-da-jogada
                data-fase="{{ $tela->fase }}" data-iniciada="{{ in_array($tela->fase, ['jogar', 'assistir'], true) ? '1' : '0' }}"
                data-agora="{{ $tela->agoraEm }}" data-expira="{{ $tela->prazoExpiraEm }}" data-libera="{{ $tela->liberaEm }}"
                data-destino="{{ route('mesas.jogo', ['id' => $id, 'partida' => $numero]) }}">
                @unless ($compacta)
                    <div>
                    <h2 class="mb-2 text-sm font-bold">{{ $partida->paralelo ? 'Nesta rodada' : 'Ordem dos jogadores' }}</h2>
                    <ol class="flex gap-2 overflow-x-auto pb-2" aria-label="Participantes da rodada" data-faixa-jogadores>
                        @foreach ($tela->ordem as $linha)
                            <li data-participante="{{ $linha['id'] }}" wire:key="faixa-{{ $linha['id'] }}" @class(['flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2', 'border-coral bg-coral/10' => $linha['atual'] || $linha['estado'] === 'Jogando', 'border-coal/10 bg-white' => !$linha['atual'] && $linha['estado'] !== 'Jogando'])
                                @if ($linha['atual']) aria-current="step" @endif title="{{ $linha['nome'] }}">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-coal text-xs font-black text-white" aria-hidden="true">{{ $linha['iniciais'] }}</span>
                                <span class="text-sm font-bold">{{ $linha['primeiroNome'] }}<span class="block text-xs font-normal">{{ $linha['estado'] }}@if ($linha['resultado']) · {{ $linha['resultado'] }}@endif</span></span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-1 text-xs text-muted">Rodada {{ $partida->rodada }}</p>
                    @if ($partida->paralelo && $partida->faltamJogar !== [])
                        <p class="mt-2 text-sm" data-test="faltam-jogar">Falta jogar: {{ implode(', ', $partida->faltamJogar) }}</p>
                    @endif
                    </div>
                @endunless
                @if ($tela->fase === 'entre-rodadas' && $tela->resultadosEntreRodadas !== null)
                    <section class="rounded-2xl border border-coal/10 bg-white p-6 sm:p-8" data-test="entre-rodadas">
                        <h1 class="text-2xl font-black">Rodada {{ $tela->resultadosEntreRodadas['rodada'] }} encerrada</h1>
                        <p class="mt-1 text-sm text-muted">Alvo: {{ number_format($tela->resultadosEntreRodadas['alvo'] / 1000, 2, ',', '.') }} s</p>
                        <ol class="mt-4 space-y-2">
                            @foreach ($tela->resultadosEntreRodadas['resultados'] as $r)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <span class="font-bold">{{ $r['posicao'] }}º {{ $r['nome'] }}</span>
                                    <span class="tabular-nums text-muted">{{ $r['tempoMedido'] !== null ? number_format($r['tempoMedido'] / 1000, 2, ',', '.') . ' s' : '—' }} · {{ number_format($r['diferenca'] / 1000, 2, ',', '.') }} s de diferença</span>
                                </li>
                            @endforeach
                        </ol>
                        @if ($partida->ehAdmin)
                            <button type="button" wire:click="iniciarProximaRodada" wire:loading.attr="disabled" class="mt-5 min-h-11 rounded-xl bg-coral px-5 font-black" data-test="iniciar-proxima-rodada">Próxima rodada</button>
                        @else
                            <p class="mt-4 text-sm text-muted">Aguardando o administrador iniciar a próxima rodada.</p>
                        @endif
                    </section>
                @endif
                @if ($partida->pausada)
                    <section class="rounded-2xl border border-coal/10 bg-white p-6 sm:p-8">
                        <h1 class="text-3xl font-black">Partida pausada</h1>
                        @if ($tela->motivoPausa)<p class="mt-3 text-sm text-muted">{{ $tela->motivoPausa }}</p>@endif
                        @if ($partida->ehAdmin)
                            <button type="button" wire:click="despausarPartida" wire:loading.attr="disabled" class="mt-4 min-h-11 rounded-xl bg-coral px-5 font-black" data-test="despausar-partida">Despausar</button>
                        @else
                            <p class="mt-3 text-sm">Aguardando o administrador retomar a partida.</p>
                        @endif
                    </section>
                @endif
                <div @class(['rounded-2xl border border-coal/10 bg-white p-4', 'hidden' => $compacta && $tela->fase === 'jogar']) data-contagem-prazo hidden>
                    <p class="text-sm font-bold" data-rotulo-prazo>{{ $partida->paralelo ? 'Inicie sua jogada em' : ($tela->fase === 'iniciar' ? 'Sua vez passa em' : 'A vez de '.$partida->vezNome.' passa em') }}</p>
                    <p class="text-3xl font-black tabular-nums" data-segundos-prazo></p>
                    <p class="mt-1 text-sm" data-instrucao-prazo>{{ $partida->paralelo ? 'Comece a jogar antes que o prazo acabe.' : 'Comece a jogar antes que sua vez passe.' }}</p>
                </div>
            </header>
        @endif
        <aside class="order-3 w-full min-w-0 space-y-4">
            <ul wire:ignore data-tempo-real-presenca class="sr-only" aria-label="Online na mesa agora"></ul>
            @if ($tela->fase !== 'fim' && $partida->ehLabirinto && !empty($partida->placar))
                @include('mesa.jogos.placar-labirinto')
            @elseif ($tela->fase !== 'fim' && !empty($partida->placar))
                <div data-placar-compacto aria-label="Placar da partida">
                    <p class="mesa-placar__titulo">Placar da partida</p>
                    <p class="mesa-placar__auxiliar mb-1">Rodada {{ $partida->rodada }}{{ $partida->metaPontos !== null ? ' · Meta: '.$partida->metaPontos.' pts' : ($partida->limiteRodadas !== null ? ' · Máx. '.$partida->limiteRodadas.' rod.' : '') }}</p>
                    @php($maxPontos = max(array_column($partida->placar, 'pontos')))
                    <ol class="mesa-placar__grade" data-lugares="{{ count($partida->placar) }}">
                        @foreach ($partida->placar as $linha)
                            <li @class(['mesa-placar__item', 'mesa-placar__item--lider' => $maxPontos > 0 && $linha['pontos'] === $maxPontos])>
                                <span class="mesa-placar__nome" title="{{ $linha['nome'] }}">{{ explode(' ', $linha['nome'])[0] }}</span>
                                <span class="mesa-placar__pontos tabular-nums">{{ $linha['pontos'] }} pts</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
            @if ($tela->fase !== 'fim' && ! $partida->ehLabirinto)
                <details class="rounded-2xl border border-coal/10 p-4" wire:ignore.self>
                    <summary class="cursor-pointer font-bold">Placar da partida</summary>
                    <ol class="mt-3 divide-y divide-coal/10">
                        @foreach ($partida->placar as $linha)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm"><span class="break-words">{{ $linha['nome'] }}</span><span class="font-black tabular-nums">{{ $linha['pontos'] }}</span></li>
                        @endforeach
                    </ol>
                </details>
            @endif
            <details class="rounded-2xl border border-coal/10 p-4">
                <summary class="cursor-pointer font-bold">Ranking da mesa</summary>
                @forelse ($ranking->linhas as $linha)
                    <p class="mt-3 flex items-center justify-between gap-3 text-sm"><span class="break-words">{{ $linha['nome'] }}</span><span class="font-black">{{ $linha['pontos'] }}</span></p>
                @empty
                    <p class="mt-3 text-sm text-muted">Nenhuma partida pontuada ainda.</p>
                @endforelse
            </details>
            <x-retorno :erros="$errors->all()" />
            @if ($partida->ehAdmin && $partida->estado === 'andamento')
                <details class="hidden rounded-2xl border border-coal/10 p-4 md:block" wire:ignore.self>
                    <summary class="cursor-pointer font-bold">Ações do admin</summary>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @if (! $partida->pausada)
                            <button type="button" wire:click="pausarPartida" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-coal/20 px-3 text-sm font-bold" data-test="pausar-partida">Pausar</button>
                        @endif
                        @if ($partida->podePularVez && $partida->vezId)
                            <button type="button" wire:click="pularVez(@js($partida->vezId))" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-coal/20 px-3 text-sm font-bold" data-test="pular-vez">Pular vez</button>
                        @endif
                        <a href="{{ route('mesas.partida.encerrar', $id) }}" wire:click.prevent="encerrarPartida" wire:confirm="Encerrar agora termina a partida sem vencedor." wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-coal/20 px-3 text-sm font-bold" data-test="encerrar-partida">Encerrar partida</a>
                    </div>
                    @foreach ($partida->admitiveis as $pessoa)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"><span class="break-words">{{ $pessoa['nome'] }}</span><button type="button" wire:click="admitirNaPartida(@js($pessoa['id']))" class="min-h-11 font-bold underline" data-test="admitir-na-partida">Admitir</button></div>
                    @endforeach
                    @foreach ($partida->placar as $linha)
                        @if ($linha['situacao'] === 'Na disputa' && $linha['id'] !== auth()->user()->identificadorNoDominio())
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"><span class="break-words">{{ $linha['nome'] }}</span><button type="button" wire:click="removerDaPartida(@js($linha['id']))" class="min-h-11 font-bold underline" data-test="remover-da-partida">Remover</button></div>
                        @endif
                    @endforeach
                </details>
            @endif
            <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-bold underline underline-offset-4">Sair da tela do jogo</a>
        </aside>
        @if ($partida->ehAdmin && $partida->estado === 'andamento')
            <div x-data="{ aberta: false }" @abrir-admin-movel.window="aberta = true" @fechar-admin-movel.window="aberta = false"
                 x-cloak x-show="aberta" class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Ações do administrador">
                <div class="absolute inset-0 bg-coal/50" @click="aberta = false"></div>
                <div class="absolute bottom-0 left-0 right-0 rounded-t-3xl bg-cream p-6" style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom, 1.5rem))">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-lg font-black">Ações do administrador</h2>
                        <button type="button" @click="aberta = false" class="flex h-9 w-9 items-center justify-center rounded-full bg-coal/10 font-bold text-coal">✕</button>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if (! $partida->pausada)
                            <button type="button" wire:click="pausarPartida" wire:loading.attr="disabled" @click="aberta = false" class="min-h-11 rounded-lg border border-coal/20 px-4 text-sm font-bold" data-test="pausar-partida-movel">Pausar</button>
                        @endif
                        @if ($partida->podePularVez && $partida->vezId)
                            <button type="button" wire:click="pularVez(@js($partida->vezId))" wire:loading.attr="disabled" @click="aberta = false" class="min-h-11 rounded-lg border border-coal/20 px-4 text-sm font-bold" data-test="pular-vez-movel">Pular vez</button>
                        @endif
                        <a href="{{ route('mesas.partida.encerrar', $id) }}" wire:click.prevent="encerrarPartida" wire:confirm="Encerrar agora termina a partida sem vencedor." wire:loading.attr="disabled" @click="aberta = false" class="min-h-11 rounded-lg border border-coal/20 px-4 text-sm font-bold" data-test="encerrar-partida-movel">Encerrar partida</a>
                    </div>
                    @foreach ($partida->admitiveis as $pessoa)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"><span class="break-words">{{ $pessoa['nome'] }}</span><button type="button" wire:click="admitirNaPartida(@js($pessoa['id']))" @click="aberta = false" class="min-h-11 font-bold underline" data-test="admitir-na-partida-movel">Admitir</button></div>
                    @endforeach
                    @foreach ($partida->placar as $linha)
                        @if ($linha['situacao'] === 'Na disputa' && $linha['id'] !== auth()->user()->identificadorNoDominio())
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"><span class="break-words">{{ $linha['nome'] }}</span><button type="button" wire:click="removerDaPartida(@js($linha['id']))" @click="aberta = false" class="min-h-11 font-bold underline" data-test="remover-da-partida-movel">Remover</button></div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
