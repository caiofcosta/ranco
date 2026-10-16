@php($iniciada = $iniciada ?? true)
<x-tela-do-jogo :id="$id" :tela="$telaDoJogo" :titulo="$iniciada ? $nomeDoParticipante.' está jogando' : ($telaDoJogo->partida->paralelo ? 'Sua jogada' : 'Vez de '.$nomeDoParticipante)">
    <section class="labirinto-painel mx-auto max-w-xl" data-labirinto-partida data-antes-de-iniciar="{{ $iniciada ? '0' : '1' }}"
        @if ($transmissao) data-transmissao="{{ json_encode($transmissao) }}" @endif
        data-destino="{{ route('mesas.jogar.resultado', $id) }}" data-csrf="{{ csrf_token() }}"
        data-mesa="{{ $id }}" data-participante="{{ $participante }}"
        data-mapa-versao="{{ $mapaDaRodada['id'] ?? 'legado' }}"
        data-jogada="{{ $jogada }}" data-partida="{{ $partida }}" data-rodada="{{ $rodada }}" data-desempate="{{ $desempate }}">
        <script type="application/json" data-definicao>{!! json_encode($mapaDaRodada['definicao'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <p class="mb-5 break-words text-sm font-semibold text-muted" data-labirinto-nome-mesa>{{ $nomeDaMesa }}</p>
        <p class="mb-3 break-words text-lg font-bold" data-labirinto-nome-jogador>{{ $participanteLocal ? 'Vez de '.$nomeDoParticipante : 'Sua vez' }}</p>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4" data-labirinto-info>
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-muted" data-labirinto-rodada>
                    Rodada {{ $rodada }}
                </p>
                <h1 class="mt-1 break-words text-2xl font-black tracking-tight">{{ $mapaDaRodada['nome'] ?? 'Labirinto' }}</h1>
            </div>
            <div class="flex items-end gap-3">
                @if ($telaDoJogo->partida->ehAdmin && $telaDoJogo->partida->estado === 'andamento')
                    <button type="button" x-data @click="$dispatch('abrir-admin-movel')" class="md:hidden flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-coal/15 text-muted active:bg-coal/5" aria-label="Ações do administrador"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg></button>
                @endif
                <div class="text-right">
                    <p class="text-xs text-muted">{{ $participanteLocal ? 'Tempo da vez' : 'Seu tempo' }}</p>
                    <p class="text-2xl font-black tabular-nums" data-tempo>0,000 s</p>
                </div>
            </div>
        </div>
        <x-retorno :erros="$errors->all()" class="mb-4" />
        <p class="mb-4 text-sm text-muted" id="instrucoes-da-vez"><x-instrucoes-labirinto /></p>
        <div class="relative w-full" data-area-tabuleiro>
            <div class="aspect-square w-full overflow-hidden rounded-2xl bg-coal shadow-sm" data-tabuleiro aria-describedby="instrucoes-da-vez"></div>
            @if (! $iniciada)
                <div class="absolute inset-0 flex items-center justify-center rounded-2xl bg-coal/50 p-4">
                    <div class="w-full max-w-sm rounded-2xl bg-white p-5 text-center">
                        <h1 class="text-xl font-black">{{ $participanteLocal ? 'Vez de '.$nomeDoParticipante : 'É a sua vez' }}</h1>
                        <form method="POST" action="{{ route('mesas.jogar.iniciar', $id) }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="jogada" value="{{ $jogada }}">
                            <input type="hidden" name="partida" value="{{ $partida }}">
                            <input type="hidden" name="rodada" value="{{ $rodada }}">
                            <input type="hidden" name="desempate" value="{{ $desempate }}">
                            <x-botao data-test="jogar-minha-vez">{{ $participanteLocal ? 'Jogar pela vez de '.$nomeDoParticipante : 'Iniciar sua vez' }}</x-botao>
                        </form>
                    </div>
                </div>
            @else
                <div class="absolute inset-0 flex items-center justify-center rounded-2xl bg-coal/50" data-preparacao hidden>
                    <div class="relative flex h-32 w-32 items-center justify-center rounded-full bg-white" role="status" aria-label="Preparação para iniciar">
                        <svg class="absolute inset-0 h-full w-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="46" fill="none" stroke="#ff684d" stroke-width="6" pathLength="100" stroke-dasharray="100" data-anel-preparacao /></svg>
                        <span class="text-5xl font-black tabular-nums" data-numero-preparacao>3</span>
                    </div>
                </div>
            @endif
        </div>
        <p class="mt-4 text-sm" role="status" data-aviso></p>
        <x-botao tipo="button" class="mt-3" data-reenviar hidden>Enviar tempo novamente</x-botao>
        <noscript><p>Ative o JavaScript para jogar o Labirinto.</p></noscript>
    </section>
    @vite('resources/js/jogos/labirinto/entrada-partida.js')
</x-tela-do-jogo>
