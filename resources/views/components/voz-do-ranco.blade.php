{{--
    Voz do RANÇO: mostra uma frase do Provocador, nunca um texto fixo. Fica junto da informação,
    nunca no lugar dela, e não bloqueia nada (princípios de produto).
--}}
@props(['rotulo' => 'comentário completamente necessário'])

<div {{ $attributes->class('ranco-voz border-l-4 border-coral bg-sand/65 px-5 py-4') }} role="status">
    <p class="mb-1 text-[10px] font-black uppercase tracking-[.18em] text-coal/70">{{ $rotulo }}</p>
    <p class="text-[15px] font-semibold leading-6 text-coal">{{ $slot }}</p>
</div>
