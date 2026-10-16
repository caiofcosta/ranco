{{-- Ação principal da tela: coral com texto grafite (contraste 5,7). Uma por tela. --}}
@props(['tipo' => 'submit'])

<button
    type="{{ $tipo }}"
    {{ $attributes->class('h-14 w-full rounded-xl bg-coral text-sm font-black uppercase tracking-[.08em] text-coal transition hover:-translate-y-px hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal active:translate-y-0 disabled:translate-y-0 disabled:cursor-not-allowed disabled:bg-coal/15 disabled:text-coal/60 disabled:shadow-none') }}
>
    {{ $slot }}
</button>
