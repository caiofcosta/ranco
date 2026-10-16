<x-layouts::app title="Histórico">
    <section aria-labelledby="historico-titulo" class="grid gap-6">
        <div>
            <h1 id="historico-titulo" class="text-2xl font-black tracking-tight">
                Suas mesas
            </h1>
            <p class="mt-2 text-sm text-muted">
                Todas as mesas de que você participou, da última atualização para a mais antiga.
            </p>
        </div>

        @if ($mesas->total() === 0)
            <p class="rounded-2xl border border-coal/10 bg-white p-5 text-sm text-muted">
                Você ainda não participou de nenhuma mesa.
            </p>
        @elseif ($mesas->isEmpty())
            <div class="rounded-2xl border border-coal/10 bg-white p-5">
                <p class="mb-3 text-sm text-muted">Nenhuma mesa nesta página.</p>
                <x-link :href="route('historico')" wire:navigate>
                    Voltar à primeira página
                </x-link>
            </div>
        @else
            <ul class="grid gap-3">
                @foreach ($mesas as $mesa)
                    <li class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-coal/10 bg-white p-5"
                        data-test="mesa-no-historico">
                        <div class="min-w-0">
                            <h2 class="break-words font-bold">{{ $mesa['nome'] }}</h2>
                            <p class="mt-1 text-sm text-muted">
                                {{ $mesa['situacao'] }} · {{ $mesa['participacao'] }}
                            </p>
                        </div>

                        @if ($mesa['podeAbrir'])
                            <x-link :href="route('mesas.mostrar', $mesa['id'])" wire:navigate>
                                Abrir mesa
                            </x-link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $mesas->onEachSide(1)->links('historico.paginacao') }}
    </section>
</x-layouts::app>
