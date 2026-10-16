<dialog x-ref="opcoes" wire:ignore.self aria-labelledby="titulo-opcoes"
    x-init="$nextTick(() => { if (@js((bool) old('comando_mesa') && $errors->any())) $el.showModal(); })"
    class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60">
    <div x-data="{ comando: @js(old('comando_mesa', 'menu')) }">
    <div class="flex items-center justify-between gap-4">
        <h2 id="titulo-opcoes" class="text-xl font-black">Opções da mesa</h2>
        <button type="button" x-on:click="$refs.opcoes.close()" class="min-h-11 px-3 font-bold">Fechar</button>
    </div>
    <p class="mt-2 text-sm text-muted">{{ $tela->nome }}</p>
    <button type="button" x-show="comando !== 'menu'" x-on:click="comando = 'menu'" class="mt-3 min-h-11 font-bold underline">← Voltar às opções</button>
    <div x-show="comando === 'menu'" class="mt-4 grid gap-3">
        @if ($tela->ehAdmin)
            <button type="button" x-on:click="comando = 'renomear'" class="min-h-11 rounded-xl border border-coal/20 bg-white p-3 text-left font-bold">Renomear mesa</button>
            @unless ($temPartidasConcluidas)
                <button type="button" data-test="cancelar-mesa" x-on:click="comando = 'cancelar'" class="mt-3 min-h-11 rounded-xl border border-danger/30 p-3 text-left font-bold text-danger">Cancelar mesa</button>
            @endunless
        @else
            <button type="button" data-test="sair-da-mesa" x-on:click="comando = 'sair'" class="min-h-11 rounded-xl border border-danger/30 p-3 text-left font-bold text-danger">Sair da mesa</button>
        @endif
    </div>
    @if ($tela->ehAdmin)
        <div x-show="comando === 'renomear'" x-cloak>
            <h3 class="mt-4 font-black">Renomear mesa</h3>
                <form method="POST" action="{{ route('mesas.renomear', $tela->id) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="comando_mesa" value="renomear">
                    @method('PATCH')
                    <x-campo nome="nome" rotulo="Nome" :value="old('nome', $tela->nomeGravado)" maxlength="40" autocomplete="off" />
                    <button type="submit" class="inline-flex h-11 items-center rounded-full bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="salvar-nome">Salvar nome</button>
                </form>
        </div>
        @unless ($temPartidasConcluidas)
            <div x-show="comando === 'cancelar'" x-cloak class="mt-4 space-y-4" data-test="confirmar-cancelar">
                <h3 class="font-black">Cancelar esta mesa?</h3>
                <p class="text-sm leading-relaxed">Essa mesa será cancelada e a galera não poderá continuar jogando nela.</p>
                <div class="flex gap-3">
                    <button type="button" x-on:click="comando = 'menu'" class="min-h-11 flex-1 rounded-xl border border-coal/20 bg-white px-4 font-bold hover:bg-coal/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Continuar na mesa</button>
                    <form method="POST" action="{{ route('mesas.encerrar', $tela->id) }}" class="flex-1">
                        @csrf
                        <button type="submit" data-test="confirmar-cancelar-mesa" class="min-h-11 w-full rounded-xl bg-danger px-4 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Sim, cancelar mesa</button>
                    </form>
                </div>
            </div>
        @endunless
    @else
        <form x-show="comando === 'sair'" x-cloak method="POST" action="{{ route('mesas.sair', $tela->id) }}" class="mt-4 space-y-4">
            @csrf
            <p>Sair da mesa é definitivo. Para voltar, é preciso novo convite.</p>
            <button type="submit" data-test="confirmar-sair" class="min-h-11 rounded-xl bg-danger px-5 font-bold text-white">Confirmar saída</button>
        </form>
    @endif
    </div>
</dialog>
