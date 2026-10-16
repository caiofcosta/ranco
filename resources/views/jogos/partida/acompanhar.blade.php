<x-tela-do-jogo :id="$id" :tela="$telaDoJogo">
    <section class="rounded-2xl border border-coal/10 bg-white p-6 sm:p-8">
        <p class="break-words text-sm font-semibold text-muted">{{ $nomeDaMesa }}</p>
        <h1 class="mt-3 text-3xl font-black">{{ $telaDoJogo->partida->pausada ? 'Partida pausada' : ($telaDoJogo->partida->paralelo ? 'Acompanhe a rodada' : 'Vez de '.$telaDoJogo->partida->vezNome) }}</h1>
        <x-retorno :erros="$errors->all()" class="mt-4" />
    </section>
</x-tela-do-jogo>
