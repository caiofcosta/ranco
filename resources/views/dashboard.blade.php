<x-layouts::app title="Início">
    <x-slot:cabecalho>
        <p class="hidden -rotate-6 text-right text-[.68rem] font-black uppercase leading-tight tracking-wide text-muted sm:block" aria-hidden="true">Jogar é fácil.<br>Perder sem ranço é que é difícil.</p>
    </x-slot:cabecalho>

    {{-- Modal de notificação para mobile --}}
    <x-onboarding-modal-mobile :modal="$modalDeNotificacao ?? null" />

    <livewire:navegacao.area-mobile :area="$areaMobile ?? 'inicio'" />
    <div class="hidden lg:block" data-desktop-inicio>
    @php
        $jogos      = app(\App\Jogos\JogosDisponiveis::class)->disponiveis();
        $coresJogos = ['coal', 'coral', 'sand', 'coal', 'sand'];
    @endphp

    {{-- Fala do Tom: mantida no DOM (ordem e data-test exigidos pelos testes),
         oculta na nova abertura editorial do desktop via CSS (.inicio-tom). --}}
    <div class="inicio-tom" data-test="inicio-cabecalho">
        <div class="inicio-tom-conteudo">
            <h1 class="inicio-tom-nome">E aí, {{ $nome }}.</h1>
            @if ($invisivel && $mesas === [])
                <p class="inicio-tom-fala">Você está invisível.</p>
            @elseif ($fala)
                <p class="inicio-tom-fala ranco-voz" role="status" data-test="fala-do-tom">{{ $fala->texto }}</p>
            @endif
        </div>
        @if ($fala)
            <div class="inicio-tom-figura" aria-hidden="true">
                <x-tom :pose="$fala->pose" :expressao="$fala->expressao" so-rosto />
            </div>
        @endif
    </div>

    {{-- 1. Abertura editorial ---------------------------------------- --}}
    <section class="inicio-abertura">
        <h1 class="inicio-abertura-titulo">Jogue com a galera.</h1>
        <p class="inicio-abertura-apoio">Adicione amigos, monte uma mesa de jogos e comece a disputa.</p>
    </section>

    {{-- 2. Dois cards grandes lado a lado ---------------------------- --}}
    <section class="inicio-social" aria-label="Atalhos">
        <a href="#inicio-secundario" class="inicio-social-card inicio-social-card--grafite" data-desktop-atalho="mesas">
            <span class="inicio-social-card-corpo">
                <span class="inicio-social-titulo">Mesas de jogos</span>
                <span class="inicio-social-texto">Crie jogos, veja convites e participe das partidas.</span>
            </span>
            <span class="inicio-social-seta" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
            </span>
            <img src="{{ asset('images/home/mesa-de-jogos.png') }}" alt="" class="inicio-social-arte" loading="lazy">
        </a>

        <a href="{{ route('amigos.index') }}" wire:navigate class="inicio-social-card inicio-social-card--coral" data-desktop-atalho="amigos">
            <span class="inicio-social-card-corpo">
                <span class="inicio-social-titulo">Amigos</span>
                <span class="inicio-social-texto">Adicione pessoas por e-mail para jogar junto.</span>
            </span>
            <span class="inicio-social-seta" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
            </span>
            <img src="{{ asset('images/home/adicionar-amigo.png') }}" alt="" class="inicio-social-arte" loading="lazy">
        </a>
    </section>

    {{-- 3. Destaque em faixa horizontal discreta --------------------- --}}
    @if ($destaque)
        <section class="inicio-destaque" aria-labelledby="inicio-destaque-titulo" data-test="destaque-desktop">
            <div class="inicio-destaque-texto">
                <p class="inicio-destaque-rotulo">{{ $destaque['rotulo'] }}</p>
                <h2 id="inicio-destaque-titulo" class="inicio-destaque-titulo">{{ $destaque['titulo'] }}</h2>
                <p class="inicio-destaque-desc">{{ $destaque['texto'] }}</p>
            </div>
            <a href="{{ route($destaque['rota']) }}" class="inicio-destaque-link" wire:navigate>{{ $destaque['acao'] }} →</a>
        </section>
    @endif

    {{-- 4. Jogar sozinho --------------------------------------------- --}}
    @if ($jogos !== [])
        <section class="inicio-solo" aria-labelledby="inicio-solo-titulo">
            <p id="inicio-solo-titulo" class="inicio-secao-rotulo">Jogar sozinho</p>
            <nav class="inicio-jogos" aria-label="Jogar sozinho">
                @foreach ($jogos as $jogo)
                    <a
                        href="{{ url('/jogar/'.$jogo['id']) }}"
                        class="inicio-peca-jogo inicio-peca--{{ $coresJogos[$loop->index % count($coresJogos)] }}"
                        style="--inicio-atraso: {{ number_format(0.22 + $loop->index * 0.05, 2) }}s"
                        wire:navigate
                    >
                        @if ($jogo['logo'])
                            <img src="{{ asset($jogo['logo']) }}" alt="" class="inicio-peca-jogo-logo">
                        @endif
                        <span class="inicio-peca-jogo-nome">{{ $jogo['nome'] }}</span>
                    </a>
                @endforeach
            </nav>
        </section>
    @endif

    {{-- 5. Mesas, convites e amizades: apresentação secundária ------- --}}
    <section class="inicio-secundario" id="inicio-secundario" aria-label="Suas mesas e amizades">
        <div class="inicio-secundario-mesas">
            {{-- Suas mesas: convites, administradas, participações e resultados --}}
            @include('inicio.suas-mesas')
        </div>
        <div class="inicio-secundario-amigos inicio-amigos">
            <livewire:amizade.amizades-na-inicio />
        </div>
    </section>

    </div>{{-- fim [data-desktop-inicio] --}}
</x-layouts::app>
