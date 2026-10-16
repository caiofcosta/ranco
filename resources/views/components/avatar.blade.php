@props(['nome', 'foto' => null])

<span {{ $attributes->class('relative grid shrink-0 place-items-center') }} aria-hidden="true">
    <span>{{ \App\Mesa\PessoasDaMesa::iniciais($nome) }}</span>
    @if ($foto)
        <img
            src="{{ $foto }}"
            alt=""
            class="absolute inset-0 size-full object-cover [border-radius:inherit]"
            referrerpolicy="no-referrer"
            x-data
            x-init="if ($el.complete && !$el.naturalWidth) $el.remove()"
            x-on:error="$el.remove()"
        >
    @endif
    {{ $slot }}
</span>
