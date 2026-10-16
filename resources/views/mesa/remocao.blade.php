{{--
    Gerenciar um participante a partir da cadeira (só admin). Abre pelo evento `abrir-remocao`,
    disparado pela cadeira clicável em `x-mesa-de-jogo`. Passo 1: identifica a pessoa e oferece
    "Remover da mesa". Passo 2: confirmação. A remoção chama o método Livewire `removerDaMesa`, que
    reutiliza a regra `Mesa::removerDaMesa` (resultados já obtidos permanecem) e sincroniza via Reverb.
--}}
@if ($tela->ehAdmin)
    <dialog x-ref="remocao" id="modal-remocao" wire:ignore.self aria-labelledby="titulo-remocao"
        x-data="{ etapa: 'acoes', id: '', nome: '', local: false, foto: null, iniciais: '' }"
        x-on:abrir-remocao.window="etapa = 'acoes'; id = $event.detail.id; nome = $event.detail.nome; local = $event.detail.local; foto = $event.detail.foto; iniciais = $event.detail.iniciais; $el.showModal()"
        class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-sm overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60"
        data-test="modal-remocao">

        {{-- Passo 1: quem é e a ação disponível. --}}
        <div x-show="etapa === 'acoes'">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="titulo-remocao" class="text-lg font-black">Participante</h2>
                <button type="button" x-on:click="$refs.remocao.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10 focus-visible:outline-2">Fechar</button>
            </div>

            <div class="flex items-center gap-3">
                <span class="relative grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-coal text-lg font-black text-cream" aria-hidden="true">
                    <span x-text="iniciais"></span>
                    <template x-if="foto">
                        <img :src="foto" alt="" class="absolute inset-0 size-full object-cover" referrerpolicy="no-referrer" x-on:error="$el.remove()">
                    </template>
                </span>
                <div class="min-w-0">
                    <p class="font-black [overflow-wrap:anywhere]" x-text="nome" data-test="remocao-nome"></p>
                    <p class="text-sm text-muted" x-show="local" x-cloak data-test="remocao-local">Joga neste aparelho</p>
                </div>
            </div>

            <button type="button" x-on:click="etapa = 'confirmar'" data-test="abrir-remover-da-mesa"
                class="mt-6 min-h-11 w-full rounded-xl bg-danger px-4 font-bold text-white hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                Remover da mesa
            </button>
        </div>

        {{-- Passo 2: confirmação. --}}
        <div x-show="etapa === 'confirmar'" x-cloak>
            <h2 class="text-xl font-black">Remover da mesa?</h2>
            <p class="mt-3 text-sm leading-relaxed">Quer tirar <strong x-text="nome"></strong> desta mesa?</p>
            @if ($temPartidasConcluidas)
                <p class="mt-2 text-sm text-muted" data-test="remocao-consequencia">Os pontos que já fez continuam no resultado.</p>
            @endif
            <div class="mt-5 flex gap-3">
                <button type="button" x-on:click="$refs.remocao.close()"
                    class="min-h-11 flex-1 rounded-xl border border-coal/20 bg-white px-4 font-bold hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                    Manter na mesa
                </button>
                <button type="button" data-test="confirmar-remocao" x-on:click="$wire.removerDaMesa(id); $refs.remocao.close()"
                    class="min-h-11 flex-1 rounded-xl bg-danger px-4 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    Remover participante
                </button>
            </div>
        </div>
    </dialog>
@endif
