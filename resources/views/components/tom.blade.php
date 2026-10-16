{{--
    Figura do Tom. O SVG entra no HTML, decorativo (aria-hidden). Pose e expressão só saem
    da lista fixa; qualquer outro valor mostra a pose base. O caminho nunca é montado com o valor cru.
--}}
@props([
    'pose' => 'base',
    'expressao' => null,
    'soRosto' => false,
])

@php
    $poses = [
        'base' => 'poses/base.svg',
        'pensando' => 'poses/pensando.svg',
        'observando' => 'poses/observando.svg',
        'julgando' => 'poses/julgando.svg',
        'de-boa' => 'poses/de-boa.svg',
        'indo-embora' => 'poses/indo-embora.svg',
    ];
    $expressoes = [
        'neutro' => 'expressoes/neutro.svg',
        'desconfiado' => 'expressoes/desconfiado.svg',
        'curioso' => 'expressoes/curioso.svg',
        'cansado' => 'expressoes/cansado.svg',
        'sarcastico' => 'expressoes/sarcastico.svg',
        'atento' => 'expressoes/atento.svg',
    ];

    $poseInformada = is_string($pose) ? $pose : '';
    $expressaoInformada = is_string($expressao) ? $expressao : null;
    $poseConhecida = array_key_exists($poseInformada, $poses);
    $expressaoConhecida = $expressaoInformada !== null && array_key_exists($expressaoInformada, $expressoes);
    $valorInvalido = ! $poseConhecida || ($expressaoInformada !== null && ! $expressaoConhecida);

    $arquivoPose = $valorInvalido ? $poses['base'] : $poses[$poseInformada];
    $arquivoCabeca = $valorInvalido || ! $expressaoConhecida ? null : $expressoes[$expressaoInformada];

    $brutoPose = file_get_contents(resource_path('svg/tom/'.$arquivoPose));
    $svgPose = is_string($brutoPose) ? $brutoPose : '';
    $svgCabeca = '';
    if ($arquivoCabeca !== null) {
        $brutoCabeca = file_get_contents(resource_path('svg/tom/'.$arquivoCabeca));
        $svgCabeca = is_string($brutoCabeca) ? $brutoCabeca : '';
    }
@endphp

<span {{ $attributes->class('inline-flex shrink-0 items-end') }} aria-hidden="true">
    @if ($svgCabeca !== '' && $soRosto)
        <span class="block size-16 lg:size-20 [&_svg]:h-full [&_svg]:w-full">{!! $svgCabeca !!}</span>
    @elseif ($svgCabeca !== '')
        <span class="block size-16 lg:hidden [&_svg]:h-full [&_svg]:w-full">{!! $svgCabeca !!}</span>
        <span class="hidden h-44 lg:block [&_svg]:h-full [&_svg]:w-auto">{!! $svgPose !!}</span>
    @else
        <span class="block h-44 [&_svg]:h-full [&_svg]:w-auto">{!! $svgPose !!}</span>
    @endif
</span>
