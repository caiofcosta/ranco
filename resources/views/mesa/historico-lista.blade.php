{{--
    Lista das partidas encerradas (mais recentes primeiro). Reaproveitada pela modal Resultados
    (aba Histórico) e pela modal "Jogos da mesa" do rodapé. Dados: App\Mesa\HistoricoDaMesa.
    $chave prefixa o wire:key para não colidir entre as duas modais da mesma tela.
--}}
@php($chave = $chave ?? 'historico')
<ol class="grid gap-3" data-test="historico-da-mesa">
    @foreach (array_reverse($historico->partidas) as $item)
        <li wire:key="{{ $chave }}-{{ $item['numero'] }}" class="rounded-2xl border border-coal/10 bg-white p-4" data-test="partida-do-historico">
            <div class="flex items-baseline justify-between gap-3">
                <h3 class="min-w-0 font-black [overflow-wrap:anywhere]">Partida {{ $item['numero'] }} · {{ $item['jogo'] }}</h3>
                @if ($item['data'])
                    <span class="shrink-0 text-xs text-muted">{{ $item['data'] }}</span>
                @endif
            </div>
            <p class="mt-1 [overflow-wrap:anywhere]">
                @if ($item['vencedor'] !== null)
                    Vencedor: <strong>{{ $item['vencedor'] }}</strong>
                @else
                    Sem vencedor
                @endif
            </p>
            @if ($item['placar'] !== [])
                <ol class="mt-3 divide-y divide-coal/10">
                    @foreach ($item['placar'] as $linha)
                        <li class="flex min-w-0 items-baseline gap-3 py-1.5">
                            @if ($linha['colocacao'] !== null)
                                <span class="w-7 shrink-0 font-bold tabular-nums text-muted">{{ $linha['colocacao'] }}º</span>
                            @endif
                            <span class="min-w-0 flex-1 [overflow-wrap:anywhere]">{{ $linha['nome'] }}</span>
                            <span class="shrink-0 tabular-nums"><strong>{{ $linha['pontos'] }}</strong> {{ $linha['pontos'] === 1 ? 'ponto' : 'pontos' }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </li>
    @endforeach
</ol>
