<x-layouts::app title="Avisos">
    <section aria-labelledby="avisos-titulo" class="grid gap-6">
        <div>
            <h1 id="avisos-titulo" class="text-2xl font-black tracking-tight">
                Avisos
            </h1>
            <p class="mt-2 text-sm text-muted">
                Mensagens do sistema. Clique em qualquer aviso para ler.
            </p>
        </div>

        @if ($itens->isEmpty())
            <p class="rounded-2xl border border-coal/10 bg-white p-5 text-sm text-muted">
                Nenhum aviso no momento.
            </p>
        @else
            <ul class="grid gap-3">
                @foreach ($itens as $item)
                    @php($modal = $item['modal'])
                    <li
                        x-data="{ aberto: false, lido: {{ $item['lida'] ? 'true' : 'false' }} }"
                        @class(['rounded-2xl border bg-white overflow-hidden'])
                        :class="lido ? 'border-coal/10' : 'border-coral/30'"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left"
                            x-on:click="
                                aberto = !aberto;
                                if (aberto && !lido) {
                                    lido = true;
                                    fetch('{{ url('modal-notificacao/visualizar') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        },
                                        body: JSON.stringify({ modal_id: {{ $modal->id }} }),
                                    });
                                }
                            "
                            :aria-expanded="aberto"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span x-show="!lido" class="inline-block size-2 shrink-0 rounded-full bg-coral" aria-label="Não lido"></span>
                                    <h2 class="break-words font-bold">{{ $modal->titulo }}</h2>
                                </div>
                                <p class="mt-1 text-sm text-muted">
                                    @switch($modal->tipo)
                                        @case('onboarding') Boas-vindas @break
                                        @case('novo_jogo') Novo jogo @break
                                        @case('aviso') Aviso @break
                                        @case('destaque') Destaque @break
                                        @default {{ ucfirst($modal->tipo) }}
                                    @endswitch
                                    · {{ $modal->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y') }}
                                </p>
                            </div>

                            <svg
                                class="size-5 shrink-0 text-muted transition-transform"
                                :class="aberto ? 'rotate-180' : ''"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div x-show="aberto" x-cloak class="border-t border-coal/10 px-5 pb-6 pt-4">
                            @if (!empty($modal->conteudo['texto_inicial']))
                                <p class="text-sm leading-relaxed text-muted">{{ $modal->conteudo['texto_inicial'] }}</p>
                            @endif

                            @if (!empty($modal->conteudo['secoes']))
                                <div class="mt-4 space-y-3">
                                    @foreach ($modal->conteudo['secoes'] as $secao)
                                        <div>
                                            <h3 class="text-sm font-black text-coal">{{ $secao['titulo'] ?? '' }}</h3>
                                            <p class="mt-1 text-xs leading-relaxed text-muted">{{ $secao['texto'] ?? '' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($modal->conteudo['texto_final']))
                                <p class="mt-4 text-xs leading-relaxed text-muted">{{ $modal->conteudo['texto_final'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts::app>
