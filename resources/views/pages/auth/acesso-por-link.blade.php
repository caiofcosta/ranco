<x-layouts::auth title="Entrar sem senha">
    <x-cabecalho-de-acesso sobretitulo="sem senha" titulo="Entrar sem senha.">
        Informe seu e-mail. A gente manda um link e finge que você nunca esqueceu nada.
    </x-cabecalho-de-acesso>

    @if (session('status'))
        <x-aviso class="mb-6">{{ session('status') }}</x-aviso>
    @endif

    <form method="POST" action="{{ route('acesso-por-link.enviar') }}" class="space-y-5">
        @csrf

        <x-campo nome="email" rotulo="E-mail" tipo="email" :value="old('email')" required autofocus autocomplete="email" placeholder="voce@email.com" />

        <x-retorno />

        <x-botao>Enviar link</x-botao>
    </form>

    <p class="mt-7 border-t border-coal/[.06] pt-6 text-center text-sm text-muted">
        Prefere a senha? <x-link :href="route('login')">Entrar com senha</x-link>
    </p>
</x-layouts::auth>
