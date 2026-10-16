{{--
    Casca do painel: grafite, marfim e Inter, como no modelo.
    Navegação do sistema, incluindo o jogo solo.
    Tela cheia (a mesa): no celular, o topo leva de volta ao nível de cima do caminho e abre as
    áreas num menu; sem caminho nem barra inferior, e o conteúdo ocupa a altura da tela.
--}}
@php
    $telaCheia = (bool) ($telaCheia ?? false);
    $voltar = $telaCheia
        ? collect(app(\App\Navegacao\CaminhoMobile::class)->itens($title ?? null))->whereNotNull('url')->last()
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body @class(['min-h-screen bg-cream font-sans text-coal antialiased', 'max-md:min-h-dvh' => $telaCheia])>
        <div @class(['min-h-screen lg:grid lg:grid-cols-[228px_1fr]', 'max-md:flex max-md:min-h-dvh max-md:flex-col' => $telaCheia])>
            <aside @class(['flex flex-wrap items-center justify-between gap-3 bg-coal px-4 py-3 text-cream lg:sticky lg:top-0 lg:h-dvh lg:flex-col lg:items-stretch lg:gap-0 lg:px-[18px] lg:pb-[18px] lg:pt-[26px]', 'ranco-topo--tela-cheia' => $telaCheia])>
                @if ($voltar)
                    <a href="{{ $voltar['url'] }}" wire:navigate aria-label="Voltar para {{ $voltar['rotulo'] }}" data-tela-cheia-voltar
                        class="inline-flex min-h-11 min-w-0 items-center gap-1 justify-self-start rounded-lg pr-2 text-sm font-bold text-cream/80 transition hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral lg:hidden">
                        <flux:icon.chevron-left class="size-5 shrink-0" />
                        <span class="truncate">{{ $voltar['rotulo'] }}</span>
                    </a>
                @endif

                <a href="{{ route('dashboard') }}" class="rounded px-1 text-[1.35rem] font-black leading-none tracking-[-0.06em] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral lg:px-2.5 lg:pb-7 lg:text-[1.55rem]" wire:navigate>
                    RANÇO<span class="text-coral">.</span>
                </a>

                <div id="ranco-navegacao" class="hidden w-full flex-col gap-4 lg:flex lg:min-h-0 lg:flex-1 lg:gap-0">
                <p class="hidden px-2.5 pb-2 text-[.61rem] font-black uppercase tracking-[.15em] text-cream/60 lg:block">Jogar</p>

                <nav class="grid min-w-0 gap-1 lg:content-start lg:gap-[5px]" aria-label="Painel">
                    <a
                        href="{{ route('dashboard') }}"
                        @class([
                            'inline-flex min-h-[46px] shrink-0 items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral',
                            'bg-cream text-coal' => request()->routeIs('dashboard'),
                            'text-cream/60 hover:bg-white/5 hover:text-cream' => ! request()->routeIs('dashboard'),
                        ])
                        @if (request()->routeIs('dashboard')) aria-current="page" @endif
                        wire:navigate
                    >
                        <span @class([
                            'grid size-[30px] place-items-center rounded-[9px]',
                            'bg-coral text-coal' => request()->routeIs('dashboard'),
                            'bg-white/[.06] text-cream' => ! request()->routeIs('dashboard'),
                        ]) aria-hidden="true">
                            <flux:icon.home variant="outline" class="size-[18px]" />
                        </span>
                        Início
                    </a>

                    @php($emJogo = request()->routeIs('jogar.*'))
                    <a href="{{ route('jogar.index') }}"
                        @class([
                            'inline-flex min-h-[46px] shrink-0 items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral',
                            'bg-cream text-coal' => $emJogo,
                            'text-cream/60 hover:bg-white/5 hover:text-cream' => ! $emJogo,
                        ])
                        @if ($emJogo) aria-current="page" @endif
                        wire:navigate
                    >
                        <span @class([
                            'grid size-[30px] place-items-center rounded-[9px]',
                            'bg-coral text-coal' => $emJogo,
                            'bg-white/[.06] text-cream' => ! $emJogo,
                        ]) aria-hidden="true">
                            <flux:icon.puzzle-piece variant="outline" class="size-[18px]" />
                        </span>
                        Jogar
                    </a>

                    @php($emAmigos = request()->routeIs('amigos.*', 'amizade.*'))
                    <a
                        href="{{ route('amigos.index') }}"
                        @class([
                            'inline-flex min-h-[46px] shrink-0 items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral',
                            'bg-cream text-coal' => $emAmigos,
                            'text-cream/60 hover:bg-white/5 hover:text-cream' => ! $emAmigos,
                        ])
                        @if ($emAmigos) aria-current="page" @endif
                        wire:navigate
                    >
                        <span @class([
                            'grid size-[30px] place-items-center rounded-[9px]',
                            'bg-coral text-coal' => $emAmigos,
                            'bg-white/[.06] text-cream' => ! $emAmigos,
                        ]) aria-hidden="true">
                            <flux:icon.users variant="outline" class="size-[18px]" />
                        </span>
                        Amigos
                    </a>

                    @php($emHistorico = request()->routeIs('historico'))
                    <a
                        href="{{ route('historico') }}"
                        @class([
                            'inline-flex min-h-[46px] shrink-0 items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral',
                            'bg-cream text-coal' => $emHistorico,
                            'text-cream/60 hover:bg-white/5 hover:text-cream' => ! $emHistorico,
                        ])
                        @if ($emHistorico) aria-current="page" @endif
                        data-test="menu-historico"
                        wire:navigate
                    >
                        <span @class([
                            'grid size-[30px] place-items-center rounded-[9px]',
                            'bg-coral text-coal' => $emHistorico,
                            'bg-white/[.06] text-cream' => ! $emHistorico,
                        ]) aria-hidden="true">
                            <flux:icon.clock variant="outline" class="size-[18px]" />
                        </span>
                        Histórico
                    </a>

                    @php($emNotificacoes = request()->routeIs('notificacoes'))
                    <a
                        href="{{ route('notificacoes') }}"
                        @class([
                            'inline-flex min-h-[46px] shrink-0 items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral',
                            'bg-cream text-coal' => $emNotificacoes,
                            'text-cream/60 hover:bg-white/5 hover:text-cream' => ! $emNotificacoes,
                        ])
                        @if ($emNotificacoes) aria-current="page" @endif
                        wire:navigate
                    >
                        <span class="relative grid size-[30px] place-items-center rounded-[9px] {{ $emNotificacoes ? 'bg-coral text-coal' : 'bg-white/[.06] text-cream' }}" aria-hidden="true">
                            <flux:icon.bell variant="outline" class="size-[18px]" />
                            @if (($naoLidasNoMenu ?? 0) > 0)
                                <span class="absolute -right-0.5 -top-0.5 flex size-[14px] items-center justify-center rounded-full bg-coral text-[.5rem] font-black text-coal ring-1 ring-coal" aria-hidden="true">
                                    {{ $naoLidasNoMenu > 9 ? '9+' : $naoLidasNoMenu }}
                                </span>
                            @endif
                        </span>
                        Avisos
                        @if (($naoLidasNoMenu ?? 0) > 0 && ! $emNotificacoes)
                            <span class="ml-auto text-[.7rem] font-black text-coral" aria-label="{{ $naoLidasNoMenu }} não lido{{ $naoLidasNoMenu > 1 ? 's' : '' }}">{{ $naoLidasNoMenu }}</span>
                        @endif
                    </a>

                    @php($emPerfil = request()->routeIs('perfil.mostrar'))
                    <a
                        href="{{ route('perfil.mostrar') }}"
                        aria-label="Medalhas e troféus"
                        @class([
                            'inline-flex min-h-[46px] shrink-0 items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral',
                            'bg-cream text-coal' => $emPerfil,
                            'text-cream/60 hover:bg-white/5 hover:text-cream' => ! $emPerfil,
                        ])
                        @if ($emPerfil) aria-current="page" @endif
                        wire:navigate
                    >
                        <span @class([
                            'grid size-[30px] place-items-center rounded-[9px]',
                            'bg-coral text-coal' => $emPerfil,
                            'bg-white/[.06] text-cream' => ! $emPerfil,
                        ]) aria-hidden="true">
                            <flux:icon.trophy variant="outline" class="size-[18px]" />
                        </span>
                        Medalhas e troféus
                    </a>
                </nav>

                <div class="grid w-full shrink-0 gap-2 border-t border-white/10 pt-4 lg:mt-auto lg:border-0 lg:pt-0">
                    @php($naConta = request()->routeIs('profile.edit', 'security.edit', 'password.confirm', 'conta.participacoes-locais'))
                    <a
                        href="{{ route('profile.edit') }}"
                        aria-label="Perfil e conta"
                        @class([
                            'grid grid-cols-[40px_1fr] items-center gap-2.5 rounded-[15px] border p-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral lg:p-3',
                            'border-coral/70 bg-white/10' => $naConta,
                            'border-white/10 bg-white/[.04]' => ! $naConta,
                        ])
                        @if ($naConta) aria-current="page" @endif
                        wire:navigate
                    >
                        <x-avatar :nome="auth()->user()->name" :foto="auth()->user()->google_avatar_url" class="size-10 rounded-[13px] bg-coral text-[.76rem] font-black text-coal" />
                        <span class="block min-w-0">
                            <span class="block truncate text-[.8rem] font-bold">{{ auth()->user()->name }}</span>
                            <span class="mt-0.5 block text-[.67rem] text-cream/60">Perfil e conta</span>
                        </span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-[46px] w-full items-center gap-[11px] rounded-[13px] px-[11px] text-[.83rem] font-extrabold text-cream/60 transition hover:bg-white/5 hover:text-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral" data-test="logout-button">
                            <span class="grid size-[30px] place-items-center rounded-[9px] bg-white/[.06] text-cream" aria-hidden="true">
                                <flux:icon.arrow-right-start-on-rectangle variant="outline" class="size-[18px]" />
                            </span>
                            Sair
                        </button>
                    </form>
                </div>
                </div>

                @if ($telaCheia)
                    <livewire:navegacao.barra-mobile :menu="true" />
                @endif
            </aside>

            <div @class(['min-w-0', 'max-md:flex max-md:flex-1 max-md:flex-col' => $telaCheia])>
                @if (filled($title ?? null))
                    <header class="sticky top-0 z-10 hidden lg:flex min-h-[74px] items-center justify-between gap-4 border-b border-coal/10 bg-cream/95 px-[clamp(22px,4vw,50px)] backdrop-blur-sm">
                        <strong class="text-[.95rem] tracking-[-0.02em]">{{ $title }}</strong>
                        {{-- Complemento opcional da página, à direita (o Início usa para a frase da marca). --}}
                        @if ($cabecalho ?? null)
                            {{ $cabecalho }}
                        @endif
                    </header>
                @endif

                <div @class(['ranco-conteudo w-full max-w-[1320px] px-[clamp(22px,4vw,50px)] py-8 pb-16', 'ranco-conteudo--tela-cheia' => $telaCheia])>
                    @unless ($telaCheia)
                        <x-mobile-breadcrumb :titulo="$title ?? null" />
                    @endunless
                    {{ $slot }}
                </div>
            </div>
        </div>

        @unless ($telaCheia)
            @persist('barra-mobile')
                <livewire:navegacao.barra-mobile />
            @endpersist
        @endunless

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
