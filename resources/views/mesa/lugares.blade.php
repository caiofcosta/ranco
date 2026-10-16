@if ($tela->ehAdmin && $partida->estado !== 'andamento')
    <dialog x-ref="lugares" wire:ignore.self aria-labelledby="titulo-lugares"
        class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-cream p-6 text-coal shadow-xl backdrop:bg-coal/60">
        <div class="flex items-center justify-between gap-4">
            <h2 id="titulo-lugares" class="text-xl font-black">Lugares da mesa</h2>
            <button type="button" x-on:click="$refs.lugares.close()" class="min-h-11 rounded-lg px-3 font-bold hover:bg-coal/10">Fechar</button>
        </div>
        <p class="mt-3 text-sm text-muted">Quantas pessoas vão jogar? Você também conta!</p>
        <form wire:submit="salvarLugares" class="mt-5">
            <label for="quantidade-lugares" class="block font-bold">Quantidade de lugares</label>
            <select id="quantidade-lugares" wire:model="quantidade_lugares" class="mt-2 min-h-11 w-full rounded-xl border border-coal/20 bg-white px-3">
                @foreach (range(2, 8) as $quantidade)
                    <option value="{{ $quantidade }}">{{ $quantidade }} lugares</option>
                @endforeach
            </select>
            @error('quantidade_lugares') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @error('partida') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            {{-- A modal só define quantos lugares; adicionar/convidar é com a modal "Pessoas". --}}
            <p class="mt-3 text-sm text-muted">Escolha os lugares da mesa. Depois, adicione quem está com você ou convide a galera.</p>
            @if (count($pessoas) > 0)
                <p class="mt-2 text-sm text-muted" data-test="lugares-ja-tem">Já tem {{ count($pessoas) }} {{ count($pessoas) === 1 ? 'pessoa' : 'pessoas' }} na mesa.</p>
            @endif
            {{-- Só com convite pendente: quem joga no mesmo aparelho não precisa de recado de convite. --}}
            @if ($convites->pendentes !== [])
                <p class="mt-2 text-sm text-muted" data-test="lugares-convite-pendente">Convite só ocupa lugar quando a pessoa entrar.</p>
            @endif
            <button type="submit" wire:loading.attr="disabled" class="mt-5 min-h-11 w-full cursor-pointer rounded-xl bg-coral px-4 font-black hover:brightness-95 disabled:opacity-50">Confirmar lugares</button>
        </form>
    </dialog>
@endif
