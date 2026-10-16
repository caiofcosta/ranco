<x-layouts::auth title="Entrar">
    <x-cabecalho-de-acesso sobretitulo="identificação" titulo="Quem é você mesmo?">
        Entre na sua conta para continuar acumulando vitórias, derrotas e argumentos questionáveis.
    </x-cabecalho-de-acesso>

    @if (session('status'))
        <x-aviso class="mb-6">{{ session('status') }}</x-aviso>
    @endif

    @if (\App\Http\Controllers\Acesso\ContaGoogleController::configurado())
        <x-botao-google />
        <x-separador texto="ou use aquilo que você decorou" />
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="remember" value="1">

        <x-campo
            nome="email"
            rotulo="E-mail"
            tipo="email"
            :value="old('email')"
            required
            autofocus
            autocomplete="email"
            placeholder="voce@email.com"
            :mostrar-erro="false"
        />

        <x-campo
            nome="password"
            rotulo="Senha"
            tipo="password"
            required
            autocomplete="current-password"
            placeholder="Sua senha"
            revelavel
            :mostrar-erro="false"
        >
            <x-slot:acao>
                <x-link discreto :href="route('password.request')" class="text-xs" aria-label="Esqueci minha senha">Esqueci</x-link>
            </x-slot:acao>
        </x-campo>

        <x-retorno :erros="$errors->all()" />

        @if (session()->has('pausa_em_minutos'))
            <x-botao disabled>Tente de novo em {{ session('pausa_em_minutos') }} min</x-botao>
        @else
            <x-botao data-test="login-button">Entrar</x-botao>
        @endif
    </form>

    <div class="mt-7 space-y-3 border-t border-coal/[.06] pt-6 text-center text-sm">
        <x-link discreto :href="route('acesso-por-link')">Entrar por link enviado por e-mail</x-link>
        <p class="text-muted">Ainda não tem conta? <x-link :href="route('register')">Criar uma</x-link></p>
    </div>
</x-layouts::auth>
