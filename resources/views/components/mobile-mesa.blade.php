@props(['contexto'])
<article class="rounded-[22px] border border-coal/10 bg-white p-5" data-mobile-mesa>
    <h2 class="break-words text-xl font-black tracking-tight">{{ $contexto['mesa']->nome }}</h2>
    <p class="mt-1 text-sm text-muted">Administrada por {{ $contexto['mesa']->criadaPor }} · {{ $contexto['mesa']->quantasPessoas }} {{ $contexto['mesa']->quantasPessoas === 1 ? 'pessoa' : 'pessoas' }}</p>
    @if ($contexto['mesa']->detalhe || $contexto['jogo'])
        <p class="mt-4 text-sm">{{ implode(' · ', array_filter([$contexto['mesa']->detalhe, $contexto['jogo']])) }}</p>
    @endif
    <p class="mt-2 text-sm font-bold">{{ $contexto['status'] }}</p>
    <a href="{{ route('mesas.mostrar', $contexto['mesa']->id) }}" wire:navigate class="mt-5 flex min-h-12 items-center justify-center rounded-xl bg-coal px-4 text-sm font-bold text-cream">Abrir mesa</a>
</article>
