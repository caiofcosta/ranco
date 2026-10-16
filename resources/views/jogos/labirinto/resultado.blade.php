<x-layouts::app title="Resultado">
    <section class="mx-auto max-w-xl rounded-2xl border border-coal/10 bg-white p-6 sm:p-9">
        <p class="text-xs font-bold uppercase tracking-widest text-muted">Labirinto · solo</p>
        <p class="mt-2 break-words font-bold">{{ $jogo->nome }} · versão {{ $jogo->numero }}</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight">Chegou.</h1>
        <dl class="my-8 grid grid-cols-2 gap-5">
            <div><dt class="text-sm text-muted">Tempo atual</dt><dd class="mt-2 text-3xl font-black tabular-nums">{{ number_format($tempo / 1000, 3, ',', '.') }} s</dd></div>
            <div><dt class="text-sm text-muted">Seu recorde</dt><dd class="mt-2 text-3xl font-black tabular-nums">{{ number_format($melhorTempo / 1000, 3, ',', '.') }} s</dd>
                @if (isset($melhores[0]))
                    @php($dataRecorde = \Illuminate\Support\Carbon::parse($melhores[0]->concluida_em, 'UTC')->timezone('America/Sao_Paulo'))
                    <dd class="mt-1 text-sm text-muted"><time datetime="{{ $dataRecorde->toDateString() }}">{{ $dataRecorde->format('d/m/Y') }}</time></dd>
                @endif
            </div>
        </dl>
        <p class="mb-4 text-sm font-bold">{{ $recorde ? 'Melhor tempo registrado.' : 'Seu melhor tempo continua o mesmo.' }}</p>
        @if ($frase)
            <x-voz-do-ranco>{{ $frase }}</x-voz-do-ranco>
        @endif
        <flux:modal name="historico-labirinto" class="w-full max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Seus 10 melhores tempos</flux:heading>
                    <flux:subheading>{{ $jogo->nome }} · versão {{ $jogo->numero }}</flux:subheading>
                </div>
                <ol class="max-h-[50dvh] space-y-3 overflow-y-auto" aria-label="Dez melhores tempos">
                    @foreach ($melhores as $item)
                        @php($data = \Illuminate\Support\Carbon::parse($item->concluida_em, 'UTC')->timezone('America/Sao_Paulo'))
                        <li class="flex items-center justify-between gap-4 border-b border-coal/10 pb-3 text-sm">
                            <strong class="tabular-nums">{{ number_format($item->tempo / 1000, 3, ',', '.') }} s</strong>
                            <time class="text-muted" datetime="{{ $data->toDateString() }}">{{ $data->format('d/m/Y') }}</time>
                        </li>
                    @endforeach
                </ol>
                <div class="flex justify-end">
                    <flux:modal.close><flux:button>Fechar</flux:button></flux:modal.close>
                </div>
            </div>
        </flux:modal>
        <form action="{{ route('jogar.labirinto.iniciar') }}" method="POST" class="mt-7">
            @csrf
            <input type="hidden" name="mapa" value="{{ $jogo->mapa_id }}">
            <x-botao>Jogar de novo</x-botao>
        </form>
        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <flux:modal.trigger name="historico-labirinto">
                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-coal/20 px-4 py-2 text-sm font-bold transition hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal w-full" aria-haspopup="dialog"><flux:icon.clock variant="mini" class="size-4" />Histórico</button>
            </flux:modal.trigger>
            <a href="{{ route('jogar.labirinto') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-coal/20 px-4 py-2 text-sm font-bold transition hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Escolher outro mapa</a>
        </div>
    </section>
</x-layouts::app>
