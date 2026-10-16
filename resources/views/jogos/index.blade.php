{{--
    Catálogo dos jogos solo: cada card inicia uma tentativa contra o próprio tempo.
--}}
<x-layouts::app title="Jogar">
    @php
        $catalogo = \App\Dominio\Jogos\CatalogoDeJogos::class;
        $jogosAtivos = app(\App\Jogos\JogosDisponiveis::class)->idsDisponiveis();
        $jogos = [
            [
                'id' => $catalogo::LABIRINTO,
                'nome' => $catalogo::nome($catalogo::LABIRINTO),
                'logo' => $catalogo::logo($catalogo::LABIRINTO),
                'descricao' => 'Leve a bolinha até a chegada. Escolha um desafio ou deixe a sorte decidir.',
                'descricaoMobile' => 'Leve a bolinha até a chegada.',
                'chamada' => 'Escolher mapa',
                'url' => route('jogar.labirinto'),
            ],
        ];
        $jogos = array_values(array_filter($jogos, static fn (array $jogo): bool => in_array($jogo['id'], $jogosAtivos, true)));
    @endphp

    <section class="mx-auto w-full max-w-5xl" aria-labelledby="jogos-titulo">
        <header class="max-w-xl">
            <h1 id="jogos-titulo" class="text-3xl font-black tracking-[-0.045em] sm:text-4xl">Jogar sozinho</h1>
            <p class="mt-3 text-base leading-relaxed text-muted">Escolha um jogo e desafie seus próprios tempos.</p>
        </header>

        <ul class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($jogos as $jogo)
                <li class="@container min-w-0 only:max-w-3xl only:sm:col-span-2 only:xl:col-span-2">
                    <a
                        href="{{ $jogo['url'] }}"
                        class="mobile-jogo-card group flex h-full min-w-0 flex-col overflow-hidden rounded-[28px] border border-coal/10 bg-white transition hover:-translate-y-1 hover:border-coal/20 hover:shadow-[0_22px_50px_rgba(32,32,31,.12)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coal active:translate-y-0 motion-reduce:transition-none motion-reduce:hover:translate-y-0 @lg:flex-row"
                    >
                        <span class="mobile-jogo-logo relative grid min-h-44 place-items-center bg-sand px-6 pb-8 pt-10 @lg:w-56 @lg:shrink-0 @lg:px-6 @lg:py-8">
                            <span class="absolute inset-x-0 top-0 h-1.5 bg-coral @lg:inset-y-0 @lg:left-0 @lg:h-auto @lg:w-1.5" aria-hidden="true"></span>
                            @if (filled($jogo['logo']))
                                <img
                                    src="{{ asset($jogo['logo']) }}"
                                    alt=""
                                    class="size-28 object-contain @lg:size-36"
                                >
                            @endif
                        </span>

                        <span class="mobile-jogo-texto flex min-w-0 flex-1 flex-col justify-center p-6 @lg:py-8 @lg:pr-8">
                            <h2 class="break-words text-2xl font-black tracking-[-0.04em] @lg:text-3xl">{{ $jogo['nome'] }}</h2>
                            <p class="mt-2 max-w-md text-sm leading-relaxed text-muted"><span class="lg:hidden">{{ $jogo['descricaoMobile'] }}</span><span class="hidden lg:inline">{{ $jogo['descricao'] }}</span></p>
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-black text-coal underline decoration-coral decoration-2 underline-offset-4 group-hover:decoration-coal">
                                {{ $jogo['chamada'] }}
                                <span aria-hidden="true" class="transition-transform group-hover:translate-x-1 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0">→</span>
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts::app>
