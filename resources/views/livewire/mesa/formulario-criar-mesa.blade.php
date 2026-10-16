<form method="POST" action="{{ route('mesas.criar') }}" class="mt-8 space-y-6">
    @csrf

    <x-campo
        nome="nome"
        rotulo="Nome"
        :value="$nome"
        wire:model="nome"
        required
        maxlength="40"
        autocomplete="off"
        autofocus
        placeholder="Nome da mesa"
    />

    <div>
        <label for="categoria-nome-mesa" class="mb-2 block text-sm font-bold text-coal">
            Qual é a turma?
        </label>

        <div class="flex">
            <select
                id="categoria-nome-mesa"
                wire:model.live="categoria"
                wire:loading.attr="disabled"
                class="relative h-12 min-w-0 flex-1 rounded-l-xl rounded-r-none border border-coal/10 bg-cream/60 px-3 text-[15px] text-coal outline-none transition focus:z-10 focus:border-coal focus:ring-4 focus:ring-coral/15 disabled:opacity-60"
                @error('categoria') aria-invalid="true" aria-describedby="categoria-nome-mesa-erro" @enderror
            >
                @foreach ($categorias as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($categoria === $valor)>
                        {{ $rotulo }}
                    </option>
                @endforeach
            </select>

            <button
                type="button"
                wire:click="outrasSugestoes"
                wire:loading.attr="disabled"
                class="-ml-px h-12 shrink-0 whitespace-nowrap rounded-r-xl border border-coal/10 bg-sand px-3 text-sm font-semibold text-coal transition hover:bg-coal/10 focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal disabled:opacity-60"
            ><span aria-hidden="true">↻</span> Outras sugestões</button>
        </div>

        <span wire:loading role="status" class="mt-2 text-sm text-muted">
            Carregando…
        </span>

        @error('categoria')
            <p id="categoria-nome-mesa-erro" class="mt-2 text-sm font-semibold text-danger">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <p class="text-sm font-bold text-coal">Sugestões</p>
        <p class="mt-1 mb-3 text-xs text-muted">
            Escolha um nome daqui ou escreva o seu.
        </p>

        <div class="flex flex-wrap gap-2" aria-live="polite">
            @forelse ($sugestoes as $sugestao)
                <button
                    type="button"
                    wire:key="nome-mesa-{{ $sugestao['id'] }}"
                    wire:click="selecionar({{ $sugestao['id'] }})"
                    wire:loading.attr="disabled"
                    data-test="sugestao-nome-mesa"
                    class="rounded-full border border-coal/10 bg-white px-3 py-2 text-sm font-semibold text-coal transition hover:border-coal/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal disabled:opacity-60"
                >{{ $sugestao['nome'] }}</button>
            @empty
                <p class="text-sm text-muted">Nenhuma sugestão nesta turma.</p>
            @endforelse
        </div>

        @error('sugestao')
            <p role="alert" class="mt-2 text-sm font-semibold text-danger">{{ $message }}</p>
        @enderror

        <p wire:offline role="status" class="mt-2 text-sm text-muted">
            Sem conexão para buscar sugestões.
        </p>
    </div>

    <x-botao data-test="confirmar-criar-mesa" wire:loading.attr="disabled">
        Criar mesa
    </x-botao>
</form>
