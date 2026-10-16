<x-tela-do-jogo :id="$id" :tela="$telaDoJogo" titulo="Assistir ao Labirinto">
    <section class="labirinto-painel mx-auto w-full min-w-0 max-w-xl" data-labirinto-assistir data-contexto="{{ json_encode($contexto) }}">
        <script type="application/json" data-definicao>{!! json_encode($mapaDaRodada['definicao'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <p class="break-words text-sm font-semibold text-muted" data-labirinto-nome-mesa>{{ $nomeDaMesa }}</p>
        <h1 class="mt-4 break-words text-2xl font-black" data-labirinto-nome-jogador>Vez de {{ $nomeDoParticipante }}</h1>
        <div class="my-5 flex flex-wrap items-center justify-between gap-3" data-labirinto-info>
            <p class="text-sm text-muted">{{ $mapaDaRodada['nome'] ?? 'Labirinto' }}<span class="inline" data-labirinto-rodada> · Rodada {{ $contexto['rodada'] }}</span></p>
            <p class="text-2xl font-black tabular-nums" data-tempo>—</p>
        </div>
        <div class="aspect-square w-full overflow-hidden rounded-2xl bg-sand" data-tabuleiro></div>
        <p class="mt-4 text-sm text-muted" role="status" data-aviso>Aguardando transmissão…</p>
        <noscript><p>Ative o JavaScript para assistir.</p></noscript>
    </section>
    @vite('resources/js/jogos/labirinto/entrada-assistir.js')
</x-tela-do-jogo>
