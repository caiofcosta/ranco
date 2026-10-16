{{-- Modal de notificações: folha inferior no mobile, caixa centralizada no desktop (lg) --}}
@props(['modal' => null])

@if ($modal)
<div
    id="onboarding-modal-mobile"
    class="fixed inset-0 z-50 flex items-end bg-coal/40 lg:items-center lg:justify-center lg:p-6 opacity-0 invisible transition-all duration-300 pointer-events-none"
    role="dialog"
    aria-labelledby="onboarding-titulo"
    data-onboarding-modal
    data-modal-id="{{ $modal->id }}"
>
    <div class="w-full bg-cream rounded-t-[30px] p-6 pb-8 shadow-[0_-10px_40px_rgba(32,32,31,.25)] pointer-events-auto lg:relative lg:max-w-lg lg:rounded-[30px] lg:p-8 lg:shadow-[0_20px_60px_rgba(32,32,31,.3)]">
        {{-- Botão fechar (X) --}}
        <button
            type="button"
            class="absolute top-4 right-4 p-2 rounded-lg hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal transition"
            aria-label="Fechar"
            data-onboarding-fechar
        >
            <svg class="size-5 text-coal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        {{-- Título --}}
        <h2 id="onboarding-titulo" class="text-2xl font-black tracking-tight text-coal">
            {{ $modal->titulo }}
        </h2>

        {{-- Texto introdutório --}}
        <p class="mt-3 text-sm text-muted leading-relaxed">
            {{ $modal->conteudo['texto_inicial'] ?? '' }}
        </p>

        {{-- Seções de explicação --}}
        @if (!empty($modal->conteudo['secoes']))
        <div class="mt-6 space-y-4">
            @foreach ($modal->conteudo['secoes'] as $secao)
            <div>
                <h3 class="text-sm font-black text-coal">{{ $secao['titulo'] ?? '' }}</h3>
                <p class="mt-1 text-xs text-muted leading-relaxed">
                    {{ $secao['texto'] ?? '' }}
                </p>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Texto final --}}
        <p class="mt-6 text-xs text-muted leading-relaxed">
            {{ $modal->conteudo['texto_final'] ?? '' }}
        </p>

        {{-- Ações --}}
        <div class="mt-6 space-y-3">
            {{-- Checkbox "Não mostrar novamente" --}}
            <label class="flex items-center gap-2 cursor-pointer hover:bg-coal/[.03] p-2 rounded-lg transition">
                <input
                    type="checkbox"
                    id="onboarding-nao-mostrar"
                    class="size-4 rounded border-coal/30 text-coral focus:ring-coral accent-coral cursor-pointer"
                    data-onboarding-nao-mostrar
                >
                <span class="text-xs text-muted font-medium">Não mostrar novamente</span>
            </label>

            {{-- Botão principal --}}
            <button
                type="button"
                class="w-full min-h-12 bg-coral text-coal font-black text-sm uppercase tracking-wider rounded-xl transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal"
                data-onboarding-botao
            >
                Entendi, bora jogar
            </button>
        </div>
    </div>
</div>
@endif
