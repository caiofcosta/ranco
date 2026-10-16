@props([
    'tipo',
    'mesa',
    'data',
])

@php
    $arquivo = [
        'vitoria' => 'trofeu-vitoria.png',
        'empate' => 'trofeu-empate.png',
        'segundo' => 'trofeu-segundo-lugar.png',
        'terceiro' => 'trofeu-terceiro-lugar.png',
    ][$tipo];

    $titulo = [
        'vitoria' => 'VITÓRIA',
        'empate' => 'EMPATE',
        'segundo' => 'SEGUNDO LUGAR',
        'terceiro' => 'TERCEIRO LUGAR',
    ][$tipo];

    $nome = [
        'vitoria' => 'Troféu de vitória',
        'empate' => 'Troféu de empate',
        'segundo' => 'Troféu de segundo lugar',
        'terceiro' => 'Troféu de terceiro lugar',
    ][$tipo];

    $empatou = $tipo === 'empate';
    $descricao = "{$nome}, mesa {$mesa}";
@endphp

<figure {{ $attributes->class('mx-auto w-full min-w-0 max-w-sm text-coal') }}>
    <div class="relative @container">
        <img
            src="{{ asset('images/trofeus/'.$arquivo) }}"
            alt="{{ $descricao }}"
            class="block h-auto w-full"
        >

        {{-- Escurece somente o texto derivado de coal, preservando a imagem e a paleta global. --}}
        <div data-test="placa-do-trofeu" @class([
            'absolute left-[15%] flex w-[70%] flex-col items-center justify-center text-center text-xs leading-tight brightness-50 [overflow-wrap:anywhere]',
            'top-[84%] h-[12%]' => ! $empatou,
            'top-[78%] h-[16%]' => $empatou,
        ])>
            <p class="w-full font-black">{{ $titulo }}</p>
            @if ($empatou)
                <p class="w-full font-semibold">FORÇA EM DOBRO</p>
            @else
                <p class="line-clamp-2 w-[90%] shrink-0 text-[clamp(9px,3cqw,12px)] font-semibold" data-test="mesa-na-placa">{{ $mesa }}</p>
            @endif
            <p class="w-full">{{ $data }}</p>
        </div>
    </div>
    <figcaption class="mt-3 text-center text-sm text-muted [overflow-wrap:anywhere]" data-test="legenda-do-trofeu">
        <p @if ($empatou) data-test="mesa-do-trofeu-empatado" @endif>{{ $mesa }}</p>
    </figcaption>
</figure>
