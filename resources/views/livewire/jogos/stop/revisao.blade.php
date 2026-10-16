{{-- Revisão: cada pessoa expande um jogador, avalia as respostas dos outros e conclui quando terminar. --}}
@php($av = $estado['avaliacoes'])
@php($pct = $av['total'] > 0 ? (int) round($av['feitas'] / $av['total'] * 100) : 0)
@php($placar = $estado['placar'] ?? null)
<section class="mt-4 space-y-5" data-revisao>
    <div>
        <p class="text-xs font-black uppercase tracking-wide text-muted">Validação</p>
        <h1 class="mt-1 text-2xl font-black">Revisão das respostas</h1>
        <p class="mt-1 text-sm text-muted">Você avalia as do outro. As suas ficam fechadas.</p>
    </div>

    <div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-coal/10" role="progressbar" aria-valuenow="{{ $av['feitas'] }}" aria-valuemin="0" aria-valuemax="{{ $av['total'] }}">
            <div class="h-full rounded-full bg-coral" style="width: {{ $pct }}%"></div>
        </div>
        <p class="mt-2 text-sm font-bold" data-progresso-avaliacoes>{{ $av['feitas'] }} de {{ $av['total'] }} respostas revisadas</p>
    </div>

    <p class="rounded-xl bg-sand/60 p-3 text-sm" data-aviso-validas>Quando todos terminarem, a rodada fecha sozinha.</p>

    @if ($placar !== null && $placar['lider'] !== null)
        <section class="overflow-hidden rounded-2xl border border-coal/10 bg-white" x-data="{ placarAberto: false }" data-placar>
            <div class="flex items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-wide text-muted">🏆 Placar da mesa</p>
                    <p class="mt-1 font-black">
                        @if (count($placar['lider']['nomes']) === 1)
                            {{ $placar['lider']['nomes'][0] }} está na frente
                        @else
                            Empate na frente
                        @endif
                        @if ($placar['modo'] === 'pontos')
                            <span class="font-bold text-muted">· {{ $placar['lider']['total'] }} de {{ $placar['meta'] }} pts</span>
                        @else
                            <span class="font-bold text-muted">· {{ $placar['lider']['total'] }} pts</span>
                        @endif
                    </p>
                    <p class="text-xs text-muted">
                        @if ($placar['modo'] === 'rodadas')
                            Rodada {{ $placar['rodada'] }} de {{ $placar['meta'] }} · ver ranking e últimas rodadas
                        @else
                            Ver ranking e últimas rodadas
                        @endif
                    </p>
                </div>
                <button type="button" @click="placarAberto = !placarAberto" :aria-expanded="placarAberto ? 'true' : 'false'"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-black" :class="placarAberto ? 'bg-coral text-coal' : 'bg-coal text-cream'"
                        x-text="placarAberto ? 'FECHAR' : 'ABRIR'" data-placar-alternar>ABRIR</button>
            </div>
            <div x-show="placarAberto" x-cloak class="space-y-4 border-t border-coal/10 p-4">
                <div class="space-y-3">
                    @foreach ($placar['ranking'] as $indice => $linha)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm font-black">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-coal/10 text-xs">{{ $indice + 1 }}º</span>
                                    <span class="truncate">{{ $linha['nome'] }}</span>
                                </span>
                                <span class="shrink-0 text-muted">{{ $linha['total'] }} pts</span>
                            </div>
                            @if ($placar['modo'] === 'pontos' && $placar['meta'] > 0)
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-coal/10">
                                    <div class="h-full rounded-full {{ $indice === 0 ? 'bg-coral' : 'bg-coal' }}" style="width: {{ min(100, (int) round($linha['total'] / $placar['meta'] * 100)) }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if (! empty($estado['historico']))
                    <div>
                        <p class="text-xs font-black uppercase tracking-wide text-muted">Últimas rodadas</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($estado['historico'] as $item)
                                <li class="flex items-start justify-between gap-3 border-t border-coal/5 pt-2 text-sm first:border-0 first:pt-0">
                                    <span class="font-black">R{{ $item['numero'] }} · {{ $item['letra'] }}</span>
                                    <span class="text-right text-muted">
                                        @if (count($item['vencedores']) === 0)
                                            sem pontuação
                                        @elseif (count($item['vencedores']) === 1)
                                            {{ $item['vencedores'][0]['nome'] }} venceu · +{{ $item['vencedores'][0]['pontos'] }} pts
                                        @else
                                            empate · {{ collect($item['vencedores'])->pluck('nome')->join(' e ') }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <ul class="space-y-3">
        @foreach ($estado['participantes'] as $participante)
            @php($aberto = in_array($participante, $expandidos, true))
            @php($meu = $participante === $estado['pessoa'])
            @php($respostasDoJogador = $estado['respostas']->get((int) $participante) ?? collect())
            <li class="rounded-2xl border bg-white {{ $meu ? 'border-coral' : 'border-coal/10' }}" data-jogador="{{ $participante }}">
                <button type="button" wire:click="alternarExpandido('{{ $participante }}')" aria-expanded="{{ $aberto ? 'true' : 'false' }}"
                        class="flex min-h-12 w-full items-center justify-between gap-3 p-4 text-left">
                    <span class="font-black">
                        {{ $estado['nomes'][$participante] ?? 'Participante' }}
                        @if ($meu)
                            <span class="ml-2 rounded-full bg-coral/20 px-2 py-0.5 text-xs font-bold" data-voce>Você</span>
                        @endif
                    </span>
                    <span class="text-sm font-bold">{{ $aberto ? 'Recolher' : 'Expandir' }}</span>
                </button>
                @if ($aberto)
                    <ul class="space-y-3 border-t border-coal/10 p-4">
                        @foreach ($estado['categorias'] as $categoria)
                            @php($resposta = $respostasDoJogador->firstWhere('categoria', $categoria))
                            <li class="{{ $meu ? 'rounded-xl bg-coral/10 p-3' : '' }}" data-categoria>
                                <span class="block text-sm font-black">{{ $categoria }}</span>
                                @if ($resposta === null || $resposta->resposta === null)
                                    <span class="text-sm text-coal/60" data-vazia>Vazia</span>
                                @else
                                    <span class="block break-words" data-resposta>{{ $resposta->resposta }}</span>
                                    @if ($meu)
                                        <span class="text-xs font-bold" data-sua-resposta>Sua resposta</span>
                                    @else
                                        @php($voto = $estado['minhas_votos'][$resposta->id] ?? null)
                                        <span class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Avaliar {{ $categoria }} de {{ $estado['nomes'][$participante] ?? 'Participante' }}">
                                            <button type="button" wire:click="votar({{ $resposta->id }}, true)" aria-pressed="{{ $voto === true ? 'true' : 'false' }}" @disabled($estado['concluiu_revisao'])
                                                    class="min-h-11 min-w-16 rounded-lg px-3 text-sm font-bold disabled:opacity-60 {{ $voto === true ? 'bg-green-600 text-white' : 'bg-green-100 text-green-900' }}">Válida</button>
                                            <button type="button" wire:click="votar({{ $resposta->id }}, false)" aria-pressed="{{ $voto === false ? 'true' : 'false' }}" @disabled($estado['concluiu_revisao'])
                                                    class="min-h-11 min-w-16 rounded-lg px-3 text-sm font-bold disabled:opacity-60 {{ $voto === false ? 'bg-red-600 text-white' : 'bg-red-100 text-red-900' }}">Inválida</button>
                                        </span>
                                        @if ($voto === null)
                                            <span class="mt-1 inline-block text-xs font-bold text-coal/60" data-pendente>Não avaliada</span>
                                        @else
                                            <span class="mt-1 inline-block rounded-full bg-coal/10 px-3 py-1 text-sm font-bold" data-voto-registrado>Você avaliou: {{ $voto ? 'Válida' : 'Inválida' }}</span>
                                        @endif
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>

    {{-- Ação decisiva no fim da tela: concluir a própria revisão trava os votos. --}}
    <div class="rounded-2xl bg-white p-4 space-y-3" data-conclusoes>
        <p class="text-sm font-black" data-contador-concluidas>{{ count(array_intersect($estado['requeridos'], $estado['concluidas'])) }} de {{ count($estado['requeridos']) }} concluíram a revisão</p>
        <ul class="flex flex-wrap gap-2">
            @foreach ($estado['requeridos'] as $participante)
                @if (in_array($participante, $estado['concluidas'], true))
                    <li class="rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-900" data-concluiu>{{ $estado['nomes'][$participante] ?? 'Participante' }}: concluiu</li>
                @else
                    <li class="rounded-full bg-coal/10 px-3 py-1 text-sm font-bold text-coal/70" data-avaliando>{{ $estado['nomes'][$participante] ?? 'Participante' }}: avaliando</li>
                @endif
            @endforeach
        </ul>
        @if ($estado['concluiu_revisao'])
            <p class="min-h-12 rounded-xl bg-coal/5 px-5 py-3 font-black" data-revisao-concluida>Você concluiu. Seus votos estão bloqueados. Aguardando os outros.</p>
        @elseif (in_array($estado['pessoa'], $estado['requeridos'], true))
            <button type="button" wire:click="concluirRevisao" wire:confirm="Depois de concluir, seus votos ficam bloqueados. Concluir agora?" wire:loading.attr="disabled"
                    class="min-h-12 w-full rounded-xl bg-coal px-5 font-black text-cream sm:w-auto" data-concluir-revisao>Concluir minha revisão</button>
        @endif
    </div>
</section>
