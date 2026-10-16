<x-layouts::app title="Labirinto · mapas">
    <section class="mx-auto max-w-4xl space-y-6">
        <a href="{{ route('jogar.index') }}" class="text-sm underline">Todos os jogos</a>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-black">Escolha seu labirinto</h1>
                <p class="mt-2 hidden text-muted lg:block">Escolha um mapa ou deixe a sorte decidir.</p>
            </div>
            @if (count($mapas))
                <form action="{{ route('jogar.labirinto.iniciar') }}" method="POST" class="sm:w-72 sm:shrink-0">@csrf<x-botao>Jogar mapa aleatório</x-botao></form>
            @endif
        </header>
        @error('mapa')<p role="alert" class="rounded-xl bg-white p-4 text-red-700">{{ $message }}</p>@enderror
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
            @forelse ($mapas as $mapa)
                <li class="min-w-0">
                    <form action="{{ route('jogar.labirinto.iniciar') }}" method="POST" class="h-full">
                        @csrf
                        <input type="hidden" name="mapa" value="{{ $mapa->mapa_id }}">
                        <button type="submit" class="group flex h-full w-full min-w-0 flex-col overflow-hidden rounded-2xl border border-coal/10 bg-white text-left transition hover:-translate-y-0.5 hover:border-coal/30 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                            <span class="block bg-sand p-3">
                                <x-previa-labirinto :linhas="json_decode($mapa->desenho, true)['linhas']" class="w-full rounded-md" />
                            </span>
                            <span class="flex flex-1 flex-col gap-2 p-3 sm:p-4">
                                <span class="break-words font-bold leading-snug"><span class="sr-only">Jogar </span>{{ $mapa->nome }}</span>
                                @if ($mapa->melhor_tempo === null)
                                    <span class="w-fit rounded-full bg-coal/5 px-2 py-0.5 text-xs font-semibold text-muted">Não jogado</span>
                                @else
                                    <span class="text-sm"><span class="text-muted">Recorde</span> <strong class="tabular-nums">{{ number_format($mapa->melhor_tempo / 1000, 3, ',', '.') }} s</strong></span>
                                @endif
                                <span aria-hidden="true" class="mt-auto pt-1 text-sm font-black text-muted transition group-hover:text-coal group-focus-visible:text-coal">Jogar →</span>
                            </span>
                        </button>
                    </form>
                </li>
            @empty
                <li class="col-span-full">Ainda não há mapas disponíveis. Volte depois que um desafio for publicado.</li>
            @endforelse
        </ul>
        @if (count($mapas))
            <p class="text-xs text-muted">O recorde vale para a versão do mapa: mapa atualizado começa do zero. Partidas de mesa ficam separadas.</p>
        @endif
    </section>
</x-layouts::app>
