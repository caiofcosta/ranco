<x-layouts::app title="Encerrar mesa">
    <div class="max-w-lg">
        <h1 class="text-2xl font-black tracking-[-0.04em]">Encerrar {{ $nome }}</h1>
        <x-aviso class="mt-6">Encerrar a mesa encerra também a partida em andamento, sem vencedor.</x-aviso>

        <form method="POST" action="{{ route('mesas.encerrar', $id) }}" class="mt-8 space-y-4">
            @csrf
            <button
                type="submit"
                class="h-14 w-full rounded-xl bg-danger text-sm font-black uppercase tracking-[.08em] text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal"
                data-test="confirmar-encerrar"
            >
                Encerrar mesa
            </button>
        </form>

        <p class="mt-6">
            <x-link :href="route('mesas.mostrar', $id)" discreto>Voltar</x-link>
        </p>
    </div>
</x-layouts::app>
