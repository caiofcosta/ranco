{{-- Link das telas: destacado (grafite, sublinhado coral) ou discreto (cinza quente). --}}
@props(['discreto' => false])

<a {{ $attributes->class([
    'rounded transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal',
    'font-semibold text-muted hover:text-coal' => $discreto,
    'font-bold text-coal underline decoration-coral decoration-2 underline-offset-4 hover:decoration-coal' => ! $discreto,
]) }}>{{ $slot }}</a>
