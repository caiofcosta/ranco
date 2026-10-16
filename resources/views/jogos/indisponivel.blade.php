<x-layouts::app title="OPS!">
    <section class="mx-auto flex min-h-[55dvh] w-full max-w-xl flex-col items-center justify-center px-4 text-center">
        <p class="text-sm font-black uppercase tracking-[0.2em] text-muted">OPS!</p>
        <h1 class="mt-3 text-4xl font-black tracking-[-0.045em] sm:text-5xl">Esse jogo não está disponível.</h1>
        <p class="mt-4 text-base leading-relaxed text-muted">Ele pode não existir ou ter sido desativado.</p>
        <a
            href="{{ route('jogar.index') }}"
            class="mt-8 inline-flex min-h-12 items-center justify-center rounded-xl bg-coral px-6 py-3 font-black text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal"
        >
            Voltar aos jogos
        </a>
    </section>
</x-layouts::app>
