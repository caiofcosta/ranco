{{--
    Painel inferior da partida de Labirinto: placar com barras de progresso, tempos da rodada
    atual (lidos de $tela->ordem) e histórico das rodadas encerradas ($partida->historicoLabirinto).
    Visível no aside compartilhado; estilos em resources/css/labirinto.css.
--}}
@php
    $metaPontos = $partida->metaPontos;
    $pontuacoes = array_column($partida->placar, 'pontos');
    $denominador = $metaPontos ?? max(1, $pontuacoes === [] ? 0 : max($pontuacoes));
    $subtitulo = $metaPontos !== null
        ? 'Primeiro a '.$metaPontos.' '.($metaPontos === 1 ? 'ponto' : 'pontos')
        : ($partida->limiteRodadas !== null
            ? $partida->limiteRodadas.' '.($partida->limiteRodadas === 1 ? 'rodada' : 'rodadas')
            : 'Sem limite de pontos');
@endphp
<section data-placar-labirinto class="lab-placar" aria-label="Placar da partida">
    <header class="lab-placar__cabecalho">
        <div class="min-w-0">
            <p class="lab-placar__titulo">Partida</p>
            <p class="lab-placar__subtitulo">{{ $subtitulo }}</p>
        </div>
        <span class="lab-placar__selo">Rodada {{ $partida->rodada }}</span>
    </header>

    <ol class="lab-placar__lista">
        @foreach ($partida->placar as $linha)
            @php($fracao = $denominador > 0 ? min(1, max(0, $linha['pontos'] / $denominador)) : 0)
            <li class="lab-placar__jogador">
                <div class="lab-placar__linha">
                    <span class="lab-placar__nome" title="{{ $linha['nome'] }}">{{ explode(' ', $linha['nome'])[0] }}</span>
                    <span class="lab-placar__pts tabular-nums">{{ $linha['pontos'] }} pts</span>
                </div>
                <div class="lab-barra">
                    <div class="lab-barra__nivel" style="width: {{ round($fracao * 100, 2) }}%"></div>
                </div>
            </li>
        @endforeach
    </ol>

    <div class="lab-secao">
        <p class="lab-secao__titulo">Tempos da rodada {{ $partida->rodada }}</p>
        <ul class="lab-tempos">
            @foreach ($tela->ordem as $linha)
                @php($jogandoAgora = $linha['atual'] || $linha['estado'] === 'Jogando')
                <li class="lab-tempos__item">
                    <span @class(['lab-tempos__nome', 'lab-tempos__nome--vez' => $jogandoAgora]) title="{{ $linha['nome'] }}">{{ $linha['primeiroNome'] }}</span>
                    <span class="lab-tempos__valor tabular-nums">
                        @if ($linha['resultado'])
                            {{ $linha['resultado'] }}
                        @elseif ($jogandoAgora)
                            Jogando agora
                        @else
                            Aguardando
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    @if (! empty($partida->historicoLabirinto))
        <div class="lab-secao">
            <p class="lab-secao__titulo">Histórico</p>
            <div class="lab-historico">
                @foreach ($partida->historicoLabirinto as $rodada)
                    <details class="lab-rodada" @if ($loop->first) open @endif>
                        <summary class="lab-rodada__resumo">
                            <span class="lab-rodada__id">Rodada {{ $rodada['rodada'] }}</span>
                            <span class="lab-rodada__ganhador">
                                @forelse ($rodada['ganhadores'] as $ganhador){{ $ganhador['primeiroNome'] }} +{{ $ganhador['pontos'] }} {{ $ganhador['pontos'] === 1 ? 'ponto' : 'pontos' }}@if (! $loop->last) · @endif @empty Sem ponto @endforelse
                            </span>
                        </summary>
                        <ul class="lab-rodada__tempos">
                            @foreach ($rodada['participantes'] as $participante)
                                <li class="lab-rodada__tempo">
                                    <span class="lab-rodada__nome" title="{{ $participante['nome'] }}">{{ $participante['primeiroNome'] }}</span>
                                    <span class="tabular-nums">{{ $participante['tempo'] ?? '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </div>
    @endif
</section>
