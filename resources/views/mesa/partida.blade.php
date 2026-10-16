<section class="md:mt-10" data-test="partida-da-mesa">
    @error('partida')
        <p class="mb-4 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror

    @if ($partida->estado === 'andamento')
        @if ($partida->podeJogarAVez && $partida->urlJogar)
            <p class="mt-6 max-w-lg">
                <a
                    href="{{ $partida->urlJogar }}"
                    data-test="jogar-minha-vez"
                    class="inline-flex h-14 w-full items-center justify-center rounded-xl bg-coral text-sm font-black uppercase tracking-[.08em] text-coal transition hover:-translate-y-px hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal"
                >{{ $partida->vezDeParticipanteLocal ? 'Jogar pela vez de '.$partida->vezNome : 'Jogar minha vez' }}</a>
            </p>
        @endif

        @if ($partida->urlAssistir)
            <p class="mt-6 max-w-lg">
                <a href="{{ $partida->urlAssistir }}" data-test="assistir-vez" class="inline-flex h-14 w-full items-center justify-center rounded-xl bg-coral text-sm font-black uppercase tracking-[.08em] text-coal transition hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Assistir</a>
            </p>
        @endif

        @if ($partida->ehAdmin)
            <div class="mt-6 flex flex-wrap gap-3">
                @if ($partida->pausada)
                    <form wire:submit="despausarPartida" method="POST" action="{{ route('mesas.partida.despausar', $partida->mesaId) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="despausar-partida">Despausar</button>
                    </form>
                @else
                    <form wire:submit="pausarPartida" method="POST" action="{{ route('mesas.partida.pausar', $partida->mesaId) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="pausar-partida">Pausar</button>
                    </form>
                @endif
                @if ($partida->podePularVez && $partida->vezId)
                    <form wire:submit="pularVez(@js($partida->vezId))" method="POST" action="{{ route('mesas.partida.pular', $partida->mesaId) }}">
                        @csrf
                        <input type="hidden" name="participante" value="{{ $partida->vezId }}">
                        <button type="submit" class="inline-flex h-11 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="pular-vez">Pular vez</button>
                    </form>
                @endif
            </div>
            <p class="mt-4">
                <x-link
                    :href="route('mesas.partida.encerrar', $partida->mesaId)"
                    wire:click.prevent="encerrarPartida"
                    wire:confirm="Encerrar agora termina a partida sem vencedor."
                    data-test="encerrar-partida"
                    class="text-sm"
                    discreto
                >Encerrar partida</x-link>
            </p>
        @endif
    @endif


    @if ($partida->ehAdmin && $partida->admitiveis !== [])
        <div class="mt-8">
            <h3 class="text-sm font-black uppercase tracking-[.12em] text-muted">Entrar na partida</h3>
            <ul class="mt-3 grid gap-2">
                @foreach ($partida->admitiveis as $pessoa)
                    <li wire:key="admitir-{{ $pessoa['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                        <span class="font-bold">{{ $pessoa['nome'] }}</span>
                        <form wire:submit="admitirNaPartida(@js($pessoa['id']))" method="POST" action="{{ route('mesas.partida.admitir', $partida->mesaId) }}">
                            @csrf
                            <input type="hidden" name="participante" value="{{ $pessoa['id'] }}">
                            <button type="submit" class="inline-flex h-10 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="admitir-na-partida">Admitir</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</section>
