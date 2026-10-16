<x-layouts::app title="Sair da mesa">
    <div class="max-w-lg">
        <h1 class="text-2xl font-black tracking-[-0.04em]">Sair de {{ $nome }}</h1>
        <x-aviso class="mt-6">Sair da mesa é definitivo. Para voltar, é preciso novo convite.</x-aviso>

        <form method="POST" action="{{ route('mesas.sair', $id) }}" class="mt-8 space-y-4">
            @csrf
            <button
                type="submit"
                class="h-14 w-full rounded-xl bg-danger text-sm font-black uppercase tracking-[.08em] text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal"
                data-test="confirmar-sair"
            >
                Sair da mesa
            </button>
        </form>

        <p class="mt-6">
            <x-link :href="route('mesas.mostrar', $id)" discreto>Voltar</x-link>
        </p>
    </div>
</x-layouts::app>
