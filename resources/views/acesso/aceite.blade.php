<x-layouts::auth.ranco :sobrio="true" title="Antes de entrar">
    <h1 class="text-3xl font-black tracking-tight">Antes de entrar</h1>
    <p class="mt-3 text-sm leading-6 text-muted">
        @if ($novaVersao)
            As regras mudaram. Leia e aceite a nova versão para continuar.
        @else
            Para usar o RANÇO, leia e aceite as regras.
        @endif
    </p>

    <x-links-documentos :versao="$versao" class="my-6 justify-start" />

    <form method="POST" action="{{ route('aceite.registrar') }}" class="grid gap-6">
        @csrf
        <input type="hidden" name="versao" value="{{ $versao }}">
        <div>
            <label for="confirmacao" class="flex items-start gap-3 text-sm leading-6">
                <input id="confirmacao" name="confirmacao" type="checkbox" value="1"
                    @checked(old('confirmacao'))
                    @if ($errors->has('confirmacao')) aria-invalid="true" aria-describedby="confirmacao-erro" @endif
                    class="mt-1 size-5 shrink-0 accent-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">
                <span>Li e aceito os Termos de Uso, a Política de Privacidade e as Regras de Conduta, e declaro ter 18 anos ou mais.</span>
            </label>
            @error('confirmacao')
                <p id="confirmacao-erro" role="alert" class="mt-2 text-sm font-semibold text-danger">{{ $message }}</p>
            @enderror
        </div>
        @error('versao')
            <p role="alert" class="text-sm font-semibold text-danger">{{ $message }}</p>
        @enderror
        <x-botao>Aceitar e entrar</x-botao>
    </form>

    <div class="mt-6 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="min-h-11 rounded px-2 text-sm font-bold text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Sair</button>
        </form>
        <x-link :href="route('conta.excluir')" :discreto="true">{{ __('Delete account') }}</x-link>
    </div>
</x-layouts::auth.ranco>
