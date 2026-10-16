@props(['sobretitulo', 'titulo'])

<div class="mb-8">
    <p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-muted">{{ $sobretitulo }}</p>
    <h1 class="text-3xl font-black tracking-[-.04em] text-coal">{{ $titulo }}</h1>
    @if ($slot->isNotEmpty())
        <p class="mt-2 text-sm leading-6 text-muted">{{ $slot }}</p>
    @endif
</div>
