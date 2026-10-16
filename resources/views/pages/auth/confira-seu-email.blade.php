<x-layouts::auth title="Confira seu e-mail">
    <x-cabecalho-de-acesso sobretitulo="confirmação" titulo="Confira seu e-mail.">
        Enviamos um link para {{ $email }}. Vai lá. A gente não começa sem você.
    </x-cabecalho-de-acesso>

    @if (session('status'))
        <x-aviso class="mb-6">{{ session('status') }}</x-aviso>
    @endif

    <form method="POST" action="{{ route('cadastro.reenviar-link') }}" class="space-y-5">
        @csrf

        <x-retorno :erros="$errors->all()" />

        <x-botao>Reenviar link</x-botao>
    </form>

    <p class="mt-7 border-t border-coal/[.06] pt-6 text-center text-sm text-muted">
        E-mail errado? <x-link :href="route('register')">Cadastrar de novo</x-link>
    </p>
</x-layouts::auth>
