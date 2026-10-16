{{-- "Suas mesas" da Início desktop: convites pendentes primeiro, depois as mesas da pessoa, com o papel em cada cartão.
     O JS (resources/js/suas-mesas-em-tempo-real.js) troca este bloco inteiro quando chega um aviso relevante. --}}
@php
    $administradas = array_values(array_filter($mesas, static fn ($mesa): bool => $mesa->criadaPor === 'você'));
    $participadas = array_values(array_filter($mesas, static fn ($mesa): bool => $mesa->criadaPor !== 'você'));
    $coresAdministradas = ['coral', 'sand', 'coal'];
    $coresParticipadas = ['sand', 'coral'];
    $semMesas = $mesas === [];
@endphp

<div class="inicio-mesas-lista" data-suas-mesas data-url="{{ route('inicio.suas-mesas') }}">

    @error('mesa')
        <p class="mb-3 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror
    @error('convite')
        <p class="mb-3 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror

    <div class="inicio-mesas-grupo">
        <p class="inicio-grupo-titulo">Suas mesas</p>
        <div class="inicio-grade-pecas">

            @foreach ($convites as $convite)
                <div
                    class="inicio-peca inicio-peca--cream"
                    style="--inicio-atraso: {{ number_format($loop->index * 0.06, 2) }}s"
                    data-test="convite-na-home"
                    @unless ($invisivel) data-presenca-convite="{{ $convite->mesaId }}" @endunless
                >
                    <x-avatar :nome="$convite->nomeConvidante" :foto="$convite->foto" class="size-14 rounded-full bg-sand text-base font-black text-coal" />
                    <span class="inicio-peca-papel" data-test="papel-da-mesa">Convite pendente</span>
                    <p class="inicio-peca-nome">{{ $convite->nomeMesa }}</p>
                    <p class="inicio-peca-meta">{{ $convite->nomeConvidante }} convidou você · {{ $convite->quantasPessoas }} {{ $convite->quantasPessoas === 1 ? 'pessoa' : 'pessoas' }}</p>
                    <div class="inicio-pendencias-acoes">
                        @if ($invisivel)
                            <span class="inicio-pendencias-sem-vaga">Fique visível.</span>
                        @else
                            <form method="POST" action="{{ route('inicio.convites.aceitar', $convite->id) }}">
                                @csrf
                                <button type="submit" class="inicio-pendencias-btn inicio-pendencias-btn--aceitar" data-test="aceitar-convite">Aceitar</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('inicio.convites.recusar', $convite->id) }}">
                            @csrf
                            <button type="submit" class="inicio-pendencias-btn inicio-pendencias-btn--recusar" data-test="recusar-convite">Recusar</button>
                        </form>
                    </div>
                </div>
            @endforeach

            @foreach ($administradas as $mesa)
                <a
                    href="{{ $mesa->acaoUrl }}"
                    class="inicio-peca inicio-peca--{{ $coresAdministradas[$loop->index % count($coresAdministradas)] }}"
                    style="--inicio-atraso: {{ number_format($loop->index * 0.06, 2) }}s"
                    data-test="{{ $mesa->acaoEJogar ? 'jogar-pela-home' : 'abrir-mesa' }}"
                    data-mesa-id="{{ $mesa->id }}"
                >
                    <span class="inicio-peca-papel" data-test="papel-da-mesa">Administrador</span>
                    <p class="inicio-peca-nome">{{ $mesa->nome }}</p>
                    <p class="inicio-peca-meta">{{ $mesa->quantasPessoas }} {{ $mesa->quantasPessoas === 1 ? 'pessoa' : 'pessoas' }}</p>
                    <div class="inicio-peca-status {{ $mesa->acaoEJogar ? 'inicio-peca-status--jogar' : '' }}">
                        <span class="inicio-peca-status-dot"></span>
                        {{ $mesa->rotulo }}@if ($mesa->detalhe) · {{ $mesa->detalhe }}@endif
                    </div>
                    <span class="inicio-peca-acao {{ $mesa->acaoEJogar ? 'inicio-peca-acao--jogar' : '' }}">{{ $mesa->acaoRotulo }}</span>
                </a>
            @endforeach

            @if ($semMesas)
                @if ($invisivel)
                    <div class="inicio-vazio" style="grid-column: 1 / -1;">
                        <p class="inicio-vazio-titulo">Você está invisível</p>
                        <p class="inicio-vazio-texto">Invisível, você não cria nem entra em mesas.</p>
                        <a href="{{ route('profile.edit') }}" class="mt-3 inline-flex h-10 items-center justify-center rounded-xl bg-coal px-4 text-xs font-black text-cream transition hover:-translate-y-px" data-test="ficar-visivel">Ficar visível</a>
                    </div>
                @else
                    <div class="inicio-vazio" style="grid-column: 1 / -1;">
                        <p class="inicio-vazio-titulo">Nenhuma mesa ainda</p>
                        <p class="inicio-vazio-texto">Crie uma mesa e convide amigos para jogar.</p>
                    </div>
                @endif
            @endif

            @if (! $invisivel && $vagasDeMesa > 0)
                <a
                    href="{{ route('mesas.formulario') }}"
                    class="inicio-peca inicio-peca-criar"
                    style="--inicio-atraso: {{ number_format(count($administradas) * 0.06, 2) }}s"
                    data-test="criar-mesa"
                >
                    <span class="inicio-peca-criar-simbolo">+</span>
                    <span class="inicio-peca-criar-rotulo">Criar mesa</span>
                    <span class="inicio-peca-criar-desc">Convide amigos</span>
                </a>
            @endif

            @foreach ($participadas as $mesa)
                <a
                    href="{{ $mesa->acaoUrl }}"
                    class="inicio-peca inicio-peca--{{ $coresParticipadas[$loop->index % count($coresParticipadas)] }}"
                    style="--inicio-atraso: {{ number_format((count($administradas) + 1 + $loop->index) * 0.06, 2) }}s"
                    data-test="{{ $mesa->acaoEJogar ? 'jogar-pela-home' : 'abrir-mesa' }}"
                    data-mesa-id="{{ $mesa->id }}"
                >
                    <span class="inicio-peca-papel" data-test="papel-da-mesa">Participante</span>
                    <p class="inicio-peca-nome">{{ $mesa->nome }}</p>
                    <p class="inicio-peca-meta">{{ $mesa->criadaPor }} · {{ $mesa->quantasPessoas }} pessoas</p>
                    <div class="inicio-peca-status {{ $mesa->acaoEJogar ? 'inicio-peca-status--jogar' : '' }}">
                        <span class="inicio-peca-status-dot"></span>
                        {{ $mesa->rotulo }}@if ($mesa->detalhe) · {{ $mesa->detalhe }}@endif
                    </div>
                    <span class="inicio-peca-acao {{ $mesa->acaoEJogar ? 'inicio-peca-acao--jogar' : '' }}">{{ $mesa->acaoRotulo }}</span>
                </a>
            @endforeach

            @foreach ($resultados as $resultado)
                <a
                    href="{{ route('mesas.mostrar', $resultado['id']) }}"
                    class="inicio-peca inicio-peca--sand"
                    data-test="resultado-pendente"
                >
                    <p class="inicio-peca-nome">{{ $resultado['nome'] }}</p>
                    <div class="inicio-peca-status">
                        <span class="inicio-peca-status-dot"></span>
                        Encerrada
                    </div>
                    <span class="inicio-peca-acao">Ver resultado</span>
                </a>
            @endforeach

        </div>
    </div>

    @if ($vagasDeMesa === 0 && ! $invisivel)
        <p class="mt-3 text-xs text-muted" data-test="limite-de-mesas">Você atingiu o limite de {{ \App\Dominio\Plataforma\Plataforma::LIMITE_DE_MESAS }} mesas administradas.</p>
    @endif

</div>
