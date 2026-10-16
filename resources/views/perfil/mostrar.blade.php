<x-layouts::app title="Perfil">
    @if ($privado)
        <section class="max-w-xl" data-test="perfil-privado">
            <h1 class="text-3xl font-black tracking-tight">Perfil privado</h1>
            @if (filled($provocacao))
                <x-voz-do-ranco class="mt-6">{{ $provocacao }}</x-voz-do-ranco>
            @endif
            <p class="mt-6 text-sm leading-relaxed">Para ver as medalhas, é preciso ser amigo. Envie um convite pela tela Amigos.</p>
            <p class="mt-6"><x-link :href="route('amigos.index')">Amigos</x-link></p>
        </section>
    @else
        <section class="max-w-7xl" data-test="perfil-medalhas">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <x-avatar :nome="$nome" :foto="$foto" class="size-14 rounded-full bg-sand text-xl font-black text-coal" />
                    <h1 class="min-w-0 text-3xl font-black tracking-tight [overflow-wrap:anywhere]">{{ $nome }}</h1>
                </div>
            </header>

            <h2 class="mt-10 text-lg font-black tracking-tight">Medalhas</h2>
            @if ($medalhas === [])
                <x-aviso class="mt-4">Nenhuma medalha ainda.</x-aviso>
            @else
                <ul class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-5">
                    @foreach ($medalhas as $medalha)
                        <li class="min-w-0 rounded-2xl border border-coal/10 bg-white p-4" data-test="medalha">
                            <x-medalha
                                :colocacao="$medalha['colocacao']"
                                :empate="$medalha['empate']"
                                :jogo="$medalha['jogo']"
                                :logo="$medalha['logo']"
                                :frase="$medalha['frase']"
                                :mesa="$medalha['mesa']"
                                :data="$medalha['data']"
                                :data-iso="$medalha['dataIso']"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($trofeus !== [])
                <section class="mt-10" aria-labelledby="trofeus-do-perfil">
                    <h2 id="trofeus-do-perfil" class="text-lg font-black tracking-tight">Troféus</h2>
                    <ul class="mt-4 grid max-w-3xl gap-6 sm:grid-cols-2">
                        @foreach ($trofeus as $trofeu)
                            <li class="min-w-0" data-test="trofeu-do-perfil">
                                <x-trofeu :tipo="$trofeu['tipo']" :mesa="$trofeu['mesa']" :data="$trofeu['data']" />
                                @if (filled($trofeu['frase']))
                                    <x-voz-do-ranco class="mt-4" data-test="frase-do-trofeu">{{ $trofeu['frase'] }}</x-voz-do-ranco>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </section>
    @endif
</x-layouts::app>
