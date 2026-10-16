@props([
    'colocacao',
    'empate' => false,
    'jogo',
    'logo' => null,
    'frase' => null,
    'mesa',
    'data',
    'dataIso' => null,
])

@php
    $imagem = \App\Conquista\Medalhas::imagem($colocacao, $empate);
@endphp

<figure {{ $attributes->class('mx-auto w-full min-w-0 text-center text-coal') }}>
    <img
        src="{{ asset($imagem['arquivo']) }}"
        alt="{{ $imagem['descricao'] }}"
        class="mx-auto block h-auto w-full max-w-[116px]"
    >

    <figcaption class="mt-3 space-y-1.5 [overflow-wrap:anywhere]">
        <p class="flex min-w-0 items-center justify-center gap-1.5 text-xs font-semibold">
            @if (filled($logo))
                <img
                    src="{{ asset($logo) }}"
                    alt=""
                    class="size-5 shrink-0 object-contain"
                >
            @endif
            <span class="min-w-0">{{ $jogo }}</span>
        </p>

        @if (filled($frase))
            <p class="text-sm font-bold leading-snug">{{ $frase }}</p>
        @endif

        <p class="text-[.7rem] leading-relaxed text-muted">
            <span>{{ $mesa }}</span>
            <span aria-hidden="true"> · </span>
            @if ($dataIso !== null)
                <time datetime="{{ $dataIso }}">{{ $data }}</time>
            @else
                <span>{{ $data }}</span>
            @endif
        </p>
    </figcaption>
</figure>
