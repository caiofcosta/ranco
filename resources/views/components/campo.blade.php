{{--
    Campo de formulário: rótulo, entrada e erro do próprio campo.
    `revelavel` acrescenta Ver/Ocultar (senhas). O slot `acao` fica ao lado do rótulo.
    `mostrarErro` desligado quando o erro aparece no retorno do formulário (ex.: login).
--}}
@props([
    'nome',
    'rotulo',
    'tipo' => 'text',
    'revelavel' => false,
    'mostrarErro' => true,
    'id' => null,
])

@php
    $idCampo = $id ?? $nome;
    $erro = $mostrarErro ? $errors->first($nome) : null;
@endphp

<div>
    <div class="mb-2 flex items-center justify-between gap-4">
        <label for="{{ $idCampo }}" class="text-sm font-bold text-coal">{{ $rotulo }}</label>
        {{ $acao ?? '' }}
    </div>

    <div class="relative">
        <input
            id="{{ $idCampo }}"
            name="{{ $nome }}"
            type="{{ $tipo }}"
            @if ($erro) aria-invalid="true" aria-describedby="{{ $idCampo }}-erro" @endif
            {{ $attributes->class([
                'h-14 w-full rounded-xl border bg-cream/60 px-4 text-[15px] text-coal outline-none transition placeholder:text-muted focus:border-coal focus:ring-4 focus:ring-coral/15',
                'pr-20' => $revelavel,
                'border-danger' => $erro,
                'border-coal/10' => ! $erro,
            ]) }}
        >

        @if ($revelavel)
            <button
                type="button"
                class="absolute right-4 top-1/2 -translate-y-1/2 rounded text-xs font-bold text-muted transition hover:text-coal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal"
                aria-controls="{{ $idCampo }}"
                aria-pressed="false"
                onclick="const campo = document.getElementById('{{ $idCampo }}'); const mostrar = campo.type === 'password'; campo.type = mostrar ? 'text' : 'password'; this.textContent = mostrar ? 'Ocultar' : 'Ver'; this.setAttribute('aria-pressed', String(mostrar));"
            >Ver</button>
        @endif
    </div>

    @if ($erro)
        <p id="{{ $idCampo }}-erro" class="mt-2 text-sm font-semibold text-danger">{{ $erro }}</p>
    @endif
</div>
