<x-layouts::app :title="$nome" :tela-cheia="true">
    <div data-test="resumo-mesa-encerrada">
        <header>
            {{-- Rótulo "Mesa encerrada" só no desktop; mobile já tem o cabeçalho compacto do layout --}}
            <p class="mb-2 hidden text-[.63rem] font-black uppercase tracking-[.16em] text-muted md:block">
                Mesa encerrada
            </p>
            <h1 class="[overflow-wrap:anywhere] text-[clamp(1.8rem,3vw,2.7rem)] font-black leading-none tracking-[-0.05em]">
                {{ $nome }}
            </h1>
        </header>

        <div data-revelacao class="revelacao mt-4 text-coal md:mt-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 data-titulo-revelacao tabindex="-1" class="text-2xl font-black">
                    <span class="md:hidden">A disputa acabou!</span>
                    <span class="hidden md:inline">Resultado final</span>
                </h2>
                <button type="button" data-pular hidden class="min-h-11 rounded-full bg-sand px-5 font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Pular</button>
            </div>
            <p data-anuncio class="sr-only" aria-live="polite" aria-atomic="true"></p>

            {{-- ===== MOBILE: pódio ===== --}}
            @php
                $posAtual = 0;
                $ptsAnt   = null;
                $gruposPodio = [];
                foreach ($ranking->linhas as $linha) {
                    if ($linha['pontos'] !== $ptsAnt) {
                        $posAtual++;
                        $ptsAnt = $linha['pontos'];
                    }
                    $gruposPodio[$posAtual][] = $linha;
                }

                $arquivoPodio = static fn(string $tipo): string => match ($tipo) {
                    'vitoria'  => 'trofeu-vitoria.png',
                    'empate'   => 'trofeu-empate.png',
                    'segundo'  => 'trofeu-segundo-lugar.png',
                    default    => 'trofeu-terceiro-lugar.png',
                };
                $altPodio = static fn(string $tipo): string => match ($tipo) {
                    'vitoria'  => 'Troféu de vitória',
                    'empate'   => 'Troféu de empate',
                    'segundo'  => 'Troféu de segundo lugar',
                    default    => 'Troféu de terceiro lugar',
                };
                $tipoPosicao = static fn(int $pos, int $qtd): string => match (true) {
                    $pos === 1 && $qtd > 1 => 'empate',
                    $pos === 1             => 'vitoria',
                    $pos === 2             => 'segundo',
                    default                => 'terceiro',
                };
                $rotuloPosicao = static fn(int $pos): string => match ($pos) {
                    1 => '1º lugar',
                    2 => '2º lugar',
                    default => '3º lugar',
                };
                // Ordem visual tradicional: 2º | 1º | 3º
                $podioOrdem = array_values(array_filter([2, 1, 3], static fn($p) => isset($gruposPodio[$p])));
                $alturaDegrau = [1 => 'podio-degrau-1', 2 => 'podio-degrau-2', 3 => 'podio-degrau-3'];
            @endphp

            @if ($trofeus !== [] && $ranking->linhas !== [])
                <div class="md:hidden">
                    {{-- Pódio --}}
                    <div class="mt-4 flex items-end justify-center gap-1">
                        @foreach ($podioOrdem as $pos)
                            @php
                                $linhasPos = $gruposPodio[$pos];
                                $qtdPos    = count($linhasPos);
                                $tipo      = $tipoPosicao($pos, $qtdPos);
                                $pontos    = $linhasPos[0]['pontos'];
                            @endphp
                            <div data-posicao="{{ $pos }}" class="podio-coluna flex min-w-0 flex-col items-center">

                                {{-- Rótulo da posição — só aparece quando há empate --}}
                                @if ($qtdPos > 1)
                                    <p class="mb-1 text-[.58rem] font-black uppercase tracking-wide text-muted">
                                        {{ $rotuloPosicao($pos) }} · Empate
                                    </p>
                                @endif

                                {{-- Troféu --}}
                                <img
                                    src="{{ asset('images/trofeus/'.$arquivoPodio($tipo)) }}"
                                    alt="{{ $altPodio($tipo) }}"
                                    class="podio-trofeu w-16 h-auto"
                                >

                                {{-- Nomes e pontos --}}
                                <div class="mt-1.5 w-full px-1 text-center">
                                    @if ($qtdPos === 2)
                                        <p class="text-[.7rem] font-bold leading-snug [overflow-wrap:anywhere]">
                                            {{ $linhasPos[0]['nome'] }}<span class="mx-0.5 text-muted"> &amp; </span>{{ $linhasPos[1]['nome'] }}
                                        </p>
                                    @elseif ($qtdPos > 2)
                                        @foreach ($linhasPos as $linhaPos)
                                            <p class="truncate text-[.7rem] font-bold leading-snug" title="{{ $linhaPos['nome'] }}">{{ $linhaPos['nome'] }}</p>
                                        @endforeach
                                    @else
                                        <p class="truncate text-[.7rem] font-bold leading-snug" title="{{ $linhasPos[0]['nome'] }}">{{ $linhasPos[0]['nome'] }}</p>
                                    @endif
                                    <p class="mt-0.5 text-[.65rem] tabular-nums text-muted">
                                        {{ $pontos }}
                                        @if ($qtdPos > 1)
                                            pts cada
                                        @elseif ($pontos === 1)
                                            pt
                                        @else
                                            pts
                                        @endif
                                    </p>
                                </div>

                                {{-- Degrau --}}
                                <div class="{{ $alturaDegrau[$pos] ?? 'podio-degrau-3' }} podio-degrau mt-2 w-full rounded-t-lg flex items-start justify-center pt-1.5">
                                    <span class="text-xs font-black text-muted/70">{{ $pos }}º</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Provocação compacta (sem caixa, sem título) --}}
                    @php $fraseMobile = collect($trofeus)->first(static fn($t) => filled($t['frase'])); @endphp
                    @if ($fraseMobile)
                        <div data-frase>
                            <p class="mt-3 text-sm italic text-muted [overflow-wrap:anywhere]" data-test="frase-do-trofeu-mobile">{{ $fraseMobile['frase'] }}</p>
                        </div>
                    @endif

                    {{-- Classificação geral --}}
                    <section data-frase class="mt-6" aria-labelledby="classificacao-geral-mobile">
                        <h3 id="classificacao-geral-mobile" class="text-sm font-black">Classificação geral</h3>
                        <div class="mt-3 grid grid-cols-2 gap-2 min-[400px]:grid-cols-4">
                            @php
                                $posClas  = 0;
                                $ptsAntes = null;
                            @endphp
                            @foreach ($ranking->linhas as $linhaC)
                                @php
                                    if ($linhaC['pontos'] !== $ptsAntes) {
                                        $posClas++;
                                        $ptsAntes = $linhaC['pontos'];
                                    }
                                @endphp
                                <div class="flex flex-col items-center rounded-xl bg-sand/60 px-2 py-2.5 text-center">
                                    <span class="text-[.65rem] font-black text-muted">{{ $posClas }}º</span>
                                    <p class="mt-0.5 w-full truncate text-xs font-bold leading-tight" title="{{ $linhaC['nome'] }}">{{ $linhaC['nome'] }}</p>
                                    <p class="mt-0.5 text-[.65rem] tabular-nums text-muted">{{ $linhaC['pontos'] }} pts</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>
            @elseif ($ranking->linhas === [])
                <p class="mt-4 text-sm text-muted md:hidden">Esta mesa foi encerrada sem partidas.</p>
            @endif

            {{-- ===== DESKTOP: ranking + troféus (estrutura original) ===== --}}
            <section class="mt-6 hidden md:block" aria-labelledby="ranking-final">
                <h3 id="ranking-final" class="text-lg font-black">Ranking final</h3>
                <ol class="mt-4 grid gap-2">
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
                        <li data-posicao="{{ $posicao }}" class="flex min-w-0 items-baseline gap-3 rounded-2xl bg-sand px-4 py-3">
                            <span class="shrink-0 font-black">{{ $posicao }}º</span>
                            <span data-nome class="min-w-0 flex-1 font-bold [overflow-wrap:anywhere]">{{ $linha['nome'] }}</span>
                            <span class="shrink-0 text-sm tabular-nums">{{ $linha['pontos'] }} {{ $linha['pontos'] === 1 ? 'ponto' : 'pontos' }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($trofeus !== [])
                <section class="mt-8 hidden md:block" aria-labelledby="trofeus-da-mesa">
                    <h3 id="trofeus-da-mesa" class="text-lg font-black">Troféus</h3>
                    <ul class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($trofeus as $trofeu)
                            <li class="min-w-0" data-test="trofeu-da-mesa">
                                <div data-trofeu>
                                    <h4 class="mb-4 text-center font-bold [overflow-wrap:anywhere]">{{ $trofeu['nome'] }}</h4>
                                    <x-trofeu :tipo="$trofeu['tipo']" :mesa="$trofeu['mesa']" :data="$trofeu['data']" />
                                </div>
                                @if (filled($trofeu['frase']))
                                    <div data-frase class="[overflow-wrap:anywhere]">
                                        <x-voz-do-ranco class="mt-4" data-test="frase-do-trofeu">{{ $trofeu['frase'] }}</x-voz-do-ranco>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
                <a data-rever href="{{ route('mesas.mostrar', ['id' => $id, 'rever' => 1]) }}" class="mt-6 hidden min-h-11 items-center rounded font-bold underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal md:inline-flex">Rever a revelação</a>
            @endif
        </div>

        @if ($animar)
            @vite('resources/js/mesa/revelacao.js')
        @endif

        {{-- ===== MOBILE: histórico + voltar ===== --}}
        <div class="mt-6 md:hidden">
            @if ($partidas !== [])
                <details class="rounded-2xl border border-coal/10 bg-white">
                    <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4 text-sm font-bold">
                        <span>Histórico dos jogos ({{ count($partidas) }})</span>
                        <span class="text-muted" aria-hidden="true">▾</span>
                    </summary>
                    <div class="space-y-4 px-5 pb-5">
                        @foreach ($partidas as $partida)
                            <div class="border-t border-coal/10 pt-4 first:border-0 first:pt-0">
                                <p class="text-xs font-black">Partida {{ $partida['numero'] }} · {{ $partida['jogo'] }}</p>
                                @if ($partida['data'])
                                    <p class="text-[.65rem] text-muted">{{ $partida['data'] }}</p>
                                @endif
                                <p class="mt-1 text-xs [overflow-wrap:anywhere]">
                                    @if ($partida['vencedor'] !== null)
                                        Vencedor: <strong>{{ $partida['vencedor'] }}</strong>
                                    @else
                                        Sem vencedor
                                    @endif
                                </p>
                                <table class="mt-3 w-full table-fixed text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-coal/10">
                                            <th scope="col" class="pb-1.5 pr-3 font-semibold">Participante</th>
                                            <th scope="col" class="pb-1.5 text-right font-semibold">Pts</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($partida['placar'] as $linhaP)
                                            <tr class="border-b border-coal/6 last:border-0">
                                                <th scope="row" class="[overflow-wrap:anywhere] py-2 pr-3 font-semibold">{{ $linhaP['nome'] }}</th>
                                                <td class="py-2 text-right font-bold tabular-nums">{{ $linhaP['pontos'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            <a href="{{ route('dashboard') }}" class="mt-4 flex min-h-11 items-center justify-center rounded-full bg-sand font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                Voltar ao início
            </a>
        </div>

        {{-- ===== DESKTOP: participantes e partidas ===== --}}
        <section class="mt-10 hidden md:block" aria-labelledby="participantes-da-mesa">
            <h2 id="participantes-da-mesa" class="text-lg font-black tracking-[-0.03em]">
                Quem participou
            </h2>
            <ul class="mt-4 grid gap-2">
                @foreach ($participantes as $participante)
                    <li class="rounded-2xl border border-coal/10 bg-white px-4 py-3">
                        <p class="[overflow-wrap:anywhere] font-bold">{{ $participante['nome'] }}</p>
                        @if ($participante['local'])
                            <p class="text-sm text-muted">Participante local</p>
                        @endif
                        @if ($participante['saida'])
                            <p class="text-sm text-muted">{{ $participante['saida'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mt-10 hidden md:block" aria-labelledby="partidas-da-mesa">
            <h2 id="partidas-da-mesa" class="text-lg font-black tracking-[-0.03em]">
                Partidas
            </h2>

            @forelse ($partidas as $partida)
                <article class="mt-4 rounded-2xl border border-coal/10 bg-white p-5">
                    <h3 class="font-black">Partida {{ $partida['numero'] }}</h3>
                    <p class="mt-2 [overflow-wrap:anywhere] text-sm">
                        @if ($partida['vencedor'] !== null)
                            Vencedor: <strong>{{ $partida['vencedor'] }}</strong>
                        @else
                            Sem vencedor
                        @endif
                    </p>

                    <table class="mt-5 w-full table-fixed text-left text-sm">
                        <caption class="sr-only">
                            Placar final da partida {{ $partida['numero'] }}
                        </caption>
                        <thead>
                            <tr class="border-b border-coal/10">
                                <th scope="col" class="pb-2 pr-4 font-semibold">Participante</th>
                                <th scope="col" class="pb-2 text-right font-semibold">Pontos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($partida['placar'] as $linha)
                                <tr class="border-b border-coal/6 last:border-0">
                                    <th scope="row" class="[overflow-wrap:anywhere] py-3 pr-4 font-semibold">
                                        {{ $linha['nome'] }}
                                    </th>
                                    <td class="py-3 text-right font-bold tabular-nums">
                                        {{ $linha['pontos'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @empty
                <p class="mt-4 text-sm text-muted">Nenhuma partida nesta mesa.</p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
