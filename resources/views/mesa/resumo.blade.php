{{-- Modal de Partida aberta por "Configurar" na navegação superior --}}
<aside aria-label="Modais da partida" data-test="resumo-na-mesa" class="hidden">

    <dialog x-ref="resumoPartida" id="modal-resumo-partida" wire:ignore.self aria-labelledby="titulo-resumo-partida"
        class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h2 id="titulo-resumo-partida" class="text-xl font-black">Partida</h2>
            <button type="button" x-on:click="$refs.resumoPartida.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10 focus-visible:outline-2">Fechar</button>
        </div>
        <div class="text-sm break-words">
    @if ($partida->estado === 'andamento')
        <h2 class="text-sm font-black">Partida em andamento</h2>
        <p class="mt-2 text-sm text-muted">
            Labirinto · Rodada {{ $partida->rodada }}
            @if ($partida->pausada)
                · Pausada
            @endif
        </p>
        @if ($partida->paralelo)
            @if ($partida->faltamJogar !== [])
                <p class="mt-3 text-sm font-bold" data-test="faltam-jogar">Falta jogar: {{ implode(', ', $partida->faltamJogar) }}</p>
            @endif
        @elseif ($partida->vezNome)
            <p class="mt-3 text-sm font-bold">{{ $partida->jogando ? $partida->vezNome.' está jogando' : 'Vez de '.$partida->vezNome }}</p>
        @endif
        @if ($partida->prazoExpiraEm)
            <p class="mt-1 text-sm text-muted">Prazo até {{ $partida->prazoExpiraEm }}</p>
        @endif

    @elseif ($partida->estado === 'encerrada')
        <h2 class="text-sm font-black">Partida encerrada</h2>
        @if ($partida->vencedorNome)
            <p class="mt-2 font-bold" data-test="vencedor-da-partida">{{ $partida->vencedorNome }} {{ $partida->variosVencedores ? 'venceram' : 'venceu' }}.</p>
        @endif
    @else
        <h2 class="text-sm font-black">{{ $partida->estado === 'configuracao' ? 'Preparando partida' : 'Aguardando jogo' }}</h2>
    @endif
    @if ($partida->placar !== [])
        <h3 class="mt-5 text-sm font-black">Placar da partida</h3>
        <ul class="mt-2 divide-y divide-coal/10">
            @foreach ($partida->placar as $linha)
                <li wire:key="placar-{{ $partida->estado }}-{{ $linha['id'] }}" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2">
                    <span>
                        <span class="block font-bold">{{ $linha['nome'] }}</span>
                        <span class="text-xs text-muted">{{ $linha['situacao'] }}</span>
                    </span>
                    <span class="font-black tabular-nums">{{ $linha['pontos'] }}</span>
                    @if ($partida->estado === 'andamento' && $partida->ehAdmin && $linha['id'] !== auth()->user()->identificadorNoDominio())
                        <form wire:submit="removerDaPartida(@js($linha['id']))" method="POST" action="{{ route('mesas.partida.remover', $partida->mesaId) }}">
                            @csrf
                            <input type="hidden" name="participante" value="{{ $linha['id'] }}">
                            <button type="submit" class="rounded text-sm font-semibold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="remover-da-partida">Remover</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>

    @endif
        </div>
    </dialog>
</aside>
