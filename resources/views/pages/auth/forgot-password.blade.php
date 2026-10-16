<x-layouts::auth title="Recuperar senha">
    <x-cabecalho-de-acesso sobretitulo="recuperação" titulo="Esqueceu a senha?">
        Acontece. Mais do que as pessoas admitem.
    </x-cabecalho-de-acesso>

    @if (session('status'))
        <x-aviso class="mb-6">{{ session('status') }}</x-aviso>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-campo nome="email" rotulo="E-mail" tipo="email" :value="old('email')" required autofocus autocomplete="email" placeholder="voce@email.com" />

        <x-retorno />

        <x-botao data-test="email-password-reset-link-button">Enviar recuperação</x-botao>
    </form>

    <p class="mt-7 border-t border-coal/[.06] pt-6 text-center text-sm text-muted">
        Lembrou? <x-link :href="route('login')">Voltar para o login</x-link>
    </p>
</x-layouts::auth>
