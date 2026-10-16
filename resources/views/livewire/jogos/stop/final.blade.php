{{-- Fim da partida: resultado final, a última rodada e o acesso à tela de resultados da mesa. --}}
<section class="mt-6 space-y-4" data-fim-partida>
    <h2 class="text-2xl font-black">Fim da partida</h2>
    @if (! empty($estado['totais']))
        <ol class="space-y-2">
            @foreach ($estado['totais'] as $posicao => $total)
                <li class="flex items-center justify-between rounded-2xl border p-4 {{ $posicao === 0 ? 'border-coral bg-coral/10' : 'border-coal/10 bg-white' }}" data-total="{{ $total['pessoa'] }}">
                    <span class="font-black">{{ $total['nome'] }}</span>
                    <span class="text-sm font-bold">{{ $total['total'] }} pts</span>
                </li>
            @endforeach
        </ol>
    @endif
    @if ($estado['resultado'] !== null)
        @include('livewire.jogos.stop.resultado', ['resultado' => $estado['resultado']])
    @endif
    <a href="{{ route('mesas.mostrar', ['id' => $id, 'resultado' => $numero]) }}" class="inline-flex min-h-12 items-center rounded-xl bg-coal px-5 font-black text-cream" data-ver-resultados-da-mesa>Ver resultados da mesa</a>
</section>
