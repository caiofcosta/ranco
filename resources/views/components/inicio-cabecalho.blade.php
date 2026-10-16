{{--
    Topo do Início: o Tom, a saudação e a frase dele. Criar mesa fica na linha de "Suas mesas".
    Sem frase do Provocador, o Tom não aparece; a saudação continua.
--}}
@props([
    'nome',
    'fala' => null,
    'semMesa' => false,
    'invisivel' => false,
])

<section class="flex flex-wrap items-center gap-x-6 gap-y-5 rounded-[28px] bg-sand/65 px-5 py-4 sm:px-8 lg:py-3" data-test="inicio-cabecalho">
    @if ($fala)
        <x-tom :pose="$fala->pose" :expressao="$fala->expressao" so-rosto />
    @endif

    <div class="min-w-0 flex-1 basis-64">
        <h1 class="text-[clamp(2rem,4.5vw,3.25rem)] lg:text-3xl font-black leading-[1.05] tracking-[-0.05em] [overflow-wrap:anywhere]">E aí, {{ $nome }}.</h1>
        @if ($semMesa && $invisivel)
            <p class="mt-2 font-bold text-coal">Você está invisível</p>
        @endif
        @if ($fala)
            <p class="ranco-voz mt-2 text-[15px] font-semibold leading-6 text-coal" role="status" data-test="fala-do-tom">{{ $fala->texto }}</p>
        @endif
    </div>

</section>
