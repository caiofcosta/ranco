{{--
    Mesas do Início: as abertas em que a pessoa está (até o limite da Plataforma) e as encerradas com
    resultado ainda não visto. Um só botão coral: a primeira mesa com a vez da pessoa; sem ela, o
    resultado mais recente; sem resultado, a mesa mais recente. A Início não mantém presença: estar
    sentado é estar na tela da mesa.
--}}
@props(['mesas' => [], 'resultados' => [], 'invisivel' => false, 'criarEmDestaque' => true, 'vagas' => 0])

@php
    $limite = \App\Dominio\Plataforma\Plataforma::LIMITE_DE_MESAS;
    // Pílula e ponto de cada situação. Vermelho só na pausa; coral nunca como texto sobre claro.
    $situacoes = [
        'Sua vez' => ['bg-coral/20 text-coal', 'bg-coral'],
        'Em jogo' => ['bg-coal text-cream', 'bg-coral'],
        'Pausada' => ['border border-danger/40 bg-white text-danger', 'bg-danger'],
        'Configurando' => ['bg-sand text-coal', 'bg-muted'],
        'Aguardando' => ['bg-sand text-coal', 'bg-muted'],
    ];
    $principalNaMesa = collect($mesas)->search(fn ($mesa) => $mesa->acaoEJogar);
    if ($principalNaMesa === false && $resultados === [] && $mesas !== []) {
        $principalNaMesa = 0;
    }
    $principalNoResultado = $principalNaMesa === false && $resultados !== [];
    $linha = 'flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[22px] border border-coal/10 bg-white p-4 sm:px-5 @2xl:grid @2xl:grid-cols-[3.5rem_minmax(0,1.3fr)_minmax(0,1fr)_11rem]';
    $botao = 'inline-flex h-11 items-center justify-center whitespace-nowrap rounded-xl px-5 text-sm font-black transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal max-sm:w-full @2xl:w-full';
    $pilula = 'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black';
    $icone = 'hidden size-14 place-items-center rounded-2xl sm:grid';
@endphp

<section aria-labelledby="mesas-titulo">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h2 id="mesas-titulo" class="flex items-center gap-3 text-2xl font-black tracking-[-0.04em]">
                <flux:icon.user-group class="size-7" />
                Suas mesas
            </h2>
            <span class="text-sm font-bold text-muted" data-test="contador-de-mesas">{{ count($mesas) }} de {{ $limite }}</span>
        </div>
        @if ($invisivel)
            <a href="{{ route('profile.edit') }}" class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-xl border border-coal/10 bg-white px-5 text-sm font-black text-coal transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="ficar-visivel">Ficar visível</a>
        @elseif ($vagas > 0)
            <a
                href="{{ route('mesas.formulario') }}"
                @class([
                    'inline-flex h-11 items-center justify-center whitespace-nowrap rounded-xl px-5 text-sm font-black text-coal transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal',
                    'bg-coral hover:shadow-lg' => $criarEmDestaque,
                    'border border-coal/10 bg-white' => ! $criarEmDestaque,
                ])
                data-test="criar-mesa"
            >+ Criar mesa</a>
        @endif
    </div>

    @if ($vagas === 0 && ! $invisivel)
        <p class="mt-3 text-sm text-muted" data-test="limite-de-mesas">Você está em {{ $limite }} mesas, o máximo. Saia de uma (ou encerre, se for sua) para abrir ou entrar em outra.</p>
    @endif

    @error('mesa')
        <p class="mt-3 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror

    @if ($mesas === [] && $resultados === [])
        <div class="mt-5 rounded-[22px] border border-dashed border-coal/20 px-5 py-6" data-test="mesas-vazio">
            <p class="font-black text-coal">Nenhuma mesa ainda</p>
            @if ($invisivel)
                <p class="mt-2 text-sm text-muted">Invisível, você não cria nem entra em mesas. Fique visível para jogar com a galera.</p>
            @else
                <ol class="mt-4 grid gap-3 text-sm text-coal @4xl:grid-cols-3">
                    <li class="flex gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-sand text-xs font-black" aria-hidden="true">1</span>
                        <span><strong class="font-black">Crie a mesa</strong> no botão ao lado. Ela é sua: você chama e você manda.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-sand text-xs font-black" aria-hidden="true">2</span>
                        <span><strong class="font-black">Convide amigos.</strong> Só quem já é seu amigo no RANÇO pode ser chamado.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-sand text-xs font-black" aria-hidden="true">3</span>
                        <span><strong class="font-black">Joguem as partidas.</strong> Quem somar mais pontos leva o troféu da mesa.</span>
                    </li>
                </ol>
                <p class="mt-4 text-xs text-muted">Você pode estar em até {{ $limite }} mesas ao mesmo tempo, mas joga em uma por vez.</p>
            @endif
        </div>
    @else
        <ul class="mt-5 grid gap-3">
            @foreach ($mesas as $indice => $mesa)
                @php
                    [$corDaSituacao, $corDoPonto] = $situacoes[$mesa->rotulo] ?? $situacoes['Aguardando'];
                @endphp
                <li class="{{ $linha }}" data-test="mesa-na-home">
                    <span @class([$icone, 'bg-coral/20' => $mesa->acaoEJogar, 'bg-sand' => ! $mesa->acaoEJogar]) aria-hidden="true">
                        <flux:icon.user-group class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1 basis-48">
                        <h3 class="text-lg font-black tracking-[-0.03em] [overflow-wrap:anywhere]">{{ $mesa->nome }}</h3>
                        <p class="text-sm text-muted">{{ $mesa->quantasPessoas }} {{ $mesa->quantasPessoas === 1 ? 'pessoa' : 'pessoas' }}@if ($mesa->criadaPor) · criada por {{ $mesa->criadaPor }}@endif</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="{{ $pilula }} {{ $corDaSituacao }}">
                            <span class="size-2 rounded-full {{ $corDoPonto }}" aria-hidden="true"></span>
                            {{ $mesa->rotulo }}
                        </span>
                        @if ($mesa->detalhe)
                            <span class="text-sm text-muted">{{ $mesa->detalhe }}</span>
                        @endif
                    </div>
                    <a
                        href="{{ $mesa->acaoUrl }}"
                        @class([
                            $botao,
                            'bg-coral text-coal hover:shadow-lg' => $principalNaMesa === $indice,
                            'bg-coal text-cream' => $principalNaMesa !== $indice,
                        ])
                        data-test="{{ $mesa->acaoEJogar ? 'jogar-pela-home' : 'abrir-mesa' }}"
                    >{{ $mesa->acaoRotulo }}</a>
                </li>
            @endforeach

            @foreach ($resultados as $resultado)
                <li class="{{ $linha }}" data-test="resultado-pendente">
                    <span class="{{ $icone }} bg-sand" aria-hidden="true">
                        <flux:icon.trophy class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1 basis-48">
                        <h3 class="text-lg font-black tracking-[-0.03em] [overflow-wrap:anywhere]">{{ $resultado['nome'] }}</h3>
                    </div>
                    <div>
                        <span class="{{ $pilula }} bg-coal/5 text-coal">
                            <span class="size-2 rounded-full bg-coal/40" aria-hidden="true"></span>
                            Encerrada
                        </span>
                    </div>
                    <a
                        href="{{ route('mesas.mostrar', $resultado['id']) }}"
                        @class([
                            $botao,
                            'bg-coral text-coal hover:shadow-lg' => $principalNoResultado && $loop->first,
                            'bg-coal text-cream' => ! ($principalNoResultado && $loop->first),
                        ])
                    >Ver resultado</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
