{{--
    Retorno de um formulário: primeiro o fato (erro em vermelho), depois a voz do RANÇO,
    se houver uma provocação para o acontecimento.
--}}
@props(['erros' => []])

@php
    $provocacao = session('provocacao');
@endphp

@if (count($erros) > 0 || filled($provocacao))
    <div {{ $attributes->class('space-y-3') }}>
        @foreach ($erros as $erro)
            <p class="text-sm font-semibold text-danger" role="alert">{{ $erro }}</p>
        @endforeach

        @if (filled($provocacao))
            <x-voz-do-ranco>{{ $provocacao }}</x-voz-do-ranco>
        @endif
    </div>
@endif
