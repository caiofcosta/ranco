@props(['contexto', 'fala' => null, 'destaque' => null])
@php
    $catalogo = \App\Dominio\Jogos\CatalogoDeJogos::class;
    $jogosSolo = app(\App\Jogos\JogosDisponiveis::class)->idsDisponiveis();
@endphp
<div class="mobile-inicio space-y-8">
    <section aria-labelledby="mobile-chamada-titulo">
        <h1 id="mobile-chamada-titulo" class="text-3xl font-black leading-[1.05] tracking-tight">Jogue com a galera</h1>
        <p class="mt-3 text-base leading-snug text-muted">Adicione amigos, monte uma mesa de jogos e comece a disputa.</p>
    </section>

    <section aria-label="Atalhos sociais" class="mobile-social">
        <a href="{{ route('mobile.jogar-juntos') }}" wire:navigate data-mobile-atalho="jogar-juntos"
            class="mobile-social-botao mobile-social-botao--grafite">
            <span class="mobile-social-titulo">Mesas de jogos</span>
            <span class="mobile-social-seta" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
            </span>
            <img src="{{ asset('images/home/mesa-de-jogos.png') }}" alt="" class="mobile-social-arte" loading="lazy">
            <span class="mobile-social-texto">Crie jogos, veja convites e participe das partidas.</span>
        </a>

        <a href="{{ route('amigos.index') }}" wire:navigate data-mobile-atalho="amigos"
            class="mobile-social-botao mobile-social-botao--coral">
            <span class="mobile-social-titulo">Amigos</span>
            <span class="mobile-social-seta" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
            </span>
            <img src="{{ asset('images/home/adicionar-amigo.png') }}" alt="" class="mobile-social-arte" loading="lazy">
            <span class="mobile-social-texto">Adicione pessoas por e-mail para jogar junto.</span>
        </a>
    </section>

    @if ($destaque)
        <section aria-labelledby="mobile-destaque-titulo" data-mobile-destaque>
            <h2 id="mobile-destaque-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">Destaque</h2>
            <div class="mt-2 rounded-2xl border border-coal/10 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-muted">{{ $destaque['rotulo'] }}</p>
                <h3 class="mt-1 text-lg font-black tracking-tight">{{ $destaque['titulo'] }}</h3>
                <p class="mt-1 text-sm text-muted">{{ $destaque['texto'] }}</p>
                <a href="{{ route($destaque['rota']) }}" wire:navigate class="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-bold underline decoration-coral decoration-2 underline-offset-4">{{ $destaque['acao'] }}<flux:icon.arrow-right class="size-4" /></a>
            </div>
        </section>
    @endif

    <section aria-labelledby="mobile-solo-titulo">
        <div class="flex items-center justify-between gap-3">
            <h2 id="mobile-solo-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">Jogar sozinho</h2>
            <a href="{{ route('jogar.index') }}" wire:navigate class="text-sm font-bold underline decoration-coral decoration-2 underline-offset-4">Ver todos</a>
        </div>
        <ul class="mt-2 divide-y divide-coal/10 border-y border-coal/10">
            @foreach ($jogosSolo as $jogo)
                <li>
                    <a href="{{ route('jogar.index') }}" wire:navigate class="grid min-h-16 grid-cols-[36px_1fr_16px] items-center gap-3 py-3">
                        @if (filled($catalogo::logo($jogo)))
                            <img src="{{ asset($catalogo::logo($jogo)) }}" alt="" class="size-9 object-contain">
                        @else
                            <span class="size-9"></span>
                        @endif
                        <span class="min-w-0 text-sm font-bold">{{ $catalogo::nome($jogo) }}</span>
                        <flux:icon.chevron-right class="size-4 text-muted" />
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</div>
