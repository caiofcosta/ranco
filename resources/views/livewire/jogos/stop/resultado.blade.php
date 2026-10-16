{{-- Resultado de uma rodada concluída: colocação, pontos e a aceitação de cada resposta. --}}
<section class="mt-6 space-y-4" data-resultado data-resultado-numero="{{ $resultado['numero'] }}">
    <h2 class="text-xl font-black">Resultado da rodada {{ $resultado['numero'] }} · letra {{ $resultado['letra'] }}</h2>
    <ol class="space-y-2">
        @foreach ($resultado['jogadores'] as $jogador)
            <li class="flex items-center justify-between gap-3 rounded-2xl border p-4 {{ $jogador['posicao'] === 1 ? 'border-coral bg-coral/10' : 'border-coal/10 bg-white' }}"
                data-colocacao="{{ $jogador['posicao'] ?? '' }}">
                <span class="font-black">
                    {{ $jogador['nome'] }}
                    @if ($jogador['posicao'] === 1)
                        <span class="ml-2 rounded-full bg-coral px-2 py-0.5 text-xs font-black text-cream" data-vencedor>Vencedor</span>
                    @endif
                </span>
                <span class="text-sm font-bold">{{ $jogador['posicao'] ? $jogador['posicao'].'º' : 'Sem colocação' }} · {{ $jogador['pontos'] }} pts</span>
            </li>
        @endforeach
    </ol>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($resultado['jogadores'] as $jogador)
            <div class="rounded-2xl border border-coal/10 bg-white p-4">
                <p class="font-black">{{ $jogador['nome'] }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse (($resultado['respostas'][$jogador['pessoa']] ?? []) as $item)
                        <li data-resposta-resultado>
                            <strong>{{ $item['categoria'] }}:</strong>
                            @if ($item['resposta'] === null)
                                <span class="text-coal/60">vazia</span>
                            @else
                                {{ $item['resposta'] }}
                                <span class="ml-1 rounded-full px-2 text-xs font-bold {{ $item['aceita'] ? 'bg-green-100' : 'bg-red-100' }}" data-{{ $item['aceita'] ? 'aceita' : 'rejeitada' }}>{{ $item['aceita'] ? 'aceita' : 'rejeitada' }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="text-coal/60">Sem respostas.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</section>
