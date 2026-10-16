<x-layouts::app title="Labirinto">
    <section class="labirinto-painel mx-auto max-w-xl min-w-0" data-labirinto data-previa="{{ $previa ? 'true' : 'false' }}" data-tentativa="{{ $previa ? '' : $jogo->id }}" data-destino="{{ $previa ? '' : route('jogar.labirinto.salvar') }}" data-csrf="{{ csrf_token() }}">
        <script type="application/json" data-definicao>{!! json_encode(json_decode($jogo->desenho, true), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ $previa ? 'Teste do administrador · sem recordes' : 'Jogar sozinho · versão '.$jogo->numero }}</p>
                <h1 class="mt-1 break-words text-2xl font-black tracking-tight">{{ $jogo->nome }}</h1>
            </div>
            <div><p class="text-xs text-muted">Seu tempo</p><p class="text-2xl font-black tabular-nums" data-tempo>0,000 s</p></div>
        </div>
        <p class="mb-4 text-sm text-muted" id="instrucoes-labirinto"><x-instrucoes-labirinto /></p>
        <div class="aspect-square w-full overflow-hidden rounded-2xl bg-coal shadow-sm" data-tabuleiro aria-describedby="instrucoes-labirinto"></div>
        <p class="mt-4 text-sm" role="status" data-aviso></p>
        <x-botao tipo="button" class="mt-3" data-reenviar hidden>Enviar tempo novamente</x-botao>
        @if ($previa)
            <x-botao tipo="button" class="mt-3" data-reiniciar>Reiniciar teste</x-botao>
            <p class="mt-4 text-sm">Pode fechar esta aba para voltar ao rascunho. Nenhum resultado será registrado.</p>
        @else
            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <form action="{{ route('jogar.labirinto.iniciar') }}" method="POST">
                    @csrf
                    <input type="hidden" name="mapa" value="{{ $jogo->mapa_id }}">
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-coal/20 px-4 py-2 text-sm font-bold transition hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Jogar de novo</button>
                </form>
                <a href="{{ route('jogar.labirinto') }}" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-coal/20 px-4 py-2 text-sm font-bold transition hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Escolher outro mapa</a>
            </div>
            <p class="mt-2 text-center text-xs text-muted">Sair ou jogar de novo descarta o tempo em andamento.</p>
        @endif
        <noscript><p>Ative o JavaScript para jogar o Labirinto.</p></noscript>
    </section>
    @vite('resources/js/jogos/labirinto/entrada.js')
</x-layouts::app>
