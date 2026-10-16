{{-- Cabeçalho funcional das fases com letra e prazo (preenchimento e revisão). Mantém os hooks
     data-stop-letra e data-tempo-pausado; o timer ao vivo usa o relógio do servidor com deslocamento. --}}
<div class="mt-4 flex items-start justify-between gap-4 rounded-3xl bg-coal p-5 text-cream">
    <div class="min-w-0">
        <p class="text-5xl font-black leading-none tracking-tight" data-stop-letra>{{ $rodada->letra }}</p>
        <p class="mt-2 text-xs font-bold text-cream/60">Rodada {{ $rodada->numero }} · {{ $rodada->fase === 'revisando' ? 'Revisão' : 'Stop' }}</p>
    </div>
    <div class="shrink-0 text-right">
        @if ($contagem['restante'] !== null)
            <p class="text-2xl font-black tabular-nums" data-tempo-pausado aria-label="Tempo restante, pausado">{{ $contagem['restante'] }}s · partida pausada</p>
        @elseif ($contagem['fim'] !== null)
            <p class="text-3xl font-black leading-none tabular-nums" x-data="{ fim: {{ $contagem['fim'] }}, deslocamento: {{ now()->timestamp }} - Math.floor(Date.now() / 1000), agora: {{ now()->timestamp }} }" x-init="agora = Math.floor(Date.now() / 1000) + deslocamento; setInterval(() => agora = Math.floor(Date.now() / 1000) + deslocamento, 250)" x-text="Math.max(0, fim - agora) + 's'" aria-label="Tempo restante"></p>
            <p class="mt-1 text-xs font-bold text-cream/60">{{ $rodada->fase === 'revisando' ? 'para encerrar' : 'para preencher' }}</p>
        @endif
    </div>
</div>
