<x-layouts::auth title="Nova senha">
    <x-cabecalho-de-acesso sobretitulo="recuperação" titulo="Nova senha.">
        Escolha uma que dê para lembrar. Ou pelo menos tente.
    </x-cabecalho-de-acesso>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <x-campo nome="email" rotulo="E-mail" tipo="email" :value="old('email', request('email'))" required autocomplete="email" />
        <x-campo nome="password" rotulo="Nova senha" tipo="password" required autofocus autocomplete="new-password" placeholder="Mínimo de 8 caracteres" revelavel />
        <x-campo nome="password_confirmation" rotulo="Confirme a nova senha" tipo="password" required autocomplete="new-password" placeholder="Repita a senha" revelavel />

        <x-retorno />

        <x-botao data-test="reset-password-button">Salvar nova senha</x-botao>
    </form>
</x-layouts::auth>
