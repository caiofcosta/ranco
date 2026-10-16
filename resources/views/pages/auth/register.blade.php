<x-layouts::auth title="Criar conta">
    <x-cabecalho-de-acesso sobretitulo="nova conta" titulo="Crie sua conta.">
        Depois a gente vê quem vai pedir revanche primeiro.
    </x-cabecalho-de-acesso>

    @if (\App\Http\Controllers\Acesso\ContaGoogleController::configurado())
        <x-botao-google />
        <x-separador texto="ou com e-mail e senha" />
    @endif

    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf

        <x-campo nome="name" rotulo="Nome" :value="old('name')" required autofocus autocomplete="name" placeholder="Como a mesa vai te chamar" />
        <x-campo nome="email" rotulo="E-mail" tipo="email" :value="old('email')" required autocomplete="email" placeholder="voce@email.com" />
        <x-campo nome="password" rotulo="Senha" tipo="password" required autocomplete="new-password" placeholder="Mínimo de 8 caracteres" revelavel />
        <x-campo nome="password_confirmation" rotulo="Confirme a senha" tipo="password" required autocomplete="new-password" placeholder="Repita a senha" revelavel />

        <x-retorno />

        <x-botao data-test="register-user-button">Criar conta</x-botao>
    </form>

    <p class="mt-7 border-t border-coal/[.06] pt-6 text-center text-sm text-muted">
        Já tem conta? <x-link :href="route('login')">Entrar</x-link>
    </p>
</x-layouts::auth>
