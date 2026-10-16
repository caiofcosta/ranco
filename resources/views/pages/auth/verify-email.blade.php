<x-layouts::auth title="Confirme seu e-mail">
    <x-cabecalho-de-acesso sobretitulo="confirmação" titulo="Confirme seu e-mail.">
        Enviamos um link para {{ auth()->user()->email }}. Vai lá. A gente não começa sem você.
    </x-cabecalho-de-acesso>

    @if (session('status') === 'verification-link-sent')
        <x-aviso class="mb-6">Enviamos um novo link de confirmação.</x-aviso>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-5">
        @csrf

        <x-retorno :erros="$errors->all()" />

        <x-botao>Reenviar link</x-botao>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-7 border-t border-coal/[.06] pt-6 text-center">
        @csrf
        <button type="submit" class="rounded text-sm font-semibold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="logout-button">
            Sair
        </button>
    </form>
</x-layouts::auth>
