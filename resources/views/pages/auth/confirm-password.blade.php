<x-layouts::app title="Confirme sua senha">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Segurança" subheading="É só para garantir que é você quem está mexendo nisso.">
        <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
            @csrf

            <x-campo nome="password" rotulo="Senha" tipo="password" required autofocus autocomplete="current-password" revelavel />

            <x-botao data-test="confirm-password-button">Confirmar</x-botao>
        </form>
    </x-pages::settings.layout>
</x-layouts::app>
