<section class="mobile-area lg:hidden" data-mobile-area="{{ $area }}" @if ($area !== 'mais') wire:poll.visible.15s @endif>
    @if ($area !== 'inicio')
    <h1 class="text-3xl font-black tracking-tight">{{ ['pedidos' => 'Pedidos', 'jogar-juntos' => 'Jogar juntos', 'mais' => 'Mais'][$area] }}</h1>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-danger/30 p-4 text-sm text-danger">
            @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
        </div>
    @endif
    @if (session('status'))<p role="status" class="mt-4 text-sm">{{ session('status') }}</p>@endif
    @if ($area === 'inicio')
        <x-mobile-inicio :contexto="$contexto" :fala="$fala" :destaque="$destaque" />
    @elseif ($area === 'pedidos')
        <p class="mt-2 text-sm text-muted">O que chegou pra você responder.</p>
        @if ($contexto['convites'] === [] && $contexto['pedidosDeAmizade'] === [])
            <div class="mt-8 border-t border-coal/10 pt-6" data-mobile-vazio>
                <p class="font-bold">Não chegou nenhum pedido por aqui.</p>
            </div>
        @endif
        @if ($contexto['convites'] !== [])
            <section class="mt-6" aria-labelledby="pedidos-jogar-titulo">
                <h2 id="pedidos-jogar-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">Para jogar</h2>
                <div class="mt-3 grid gap-4">
                    @foreach ($contexto['convites'] as $convite)
                        <article wire:key="convite-{{ $convite->id }}" class="rounded-[22px] border border-coal/10 bg-white p-5">
                            <p class="text-xs font-black uppercase tracking-wide text-muted">Convite para mesa</p>
                            <h3 class="mt-2 break-words text-xl font-black">{{ $convite->nomeMesa }}</h3>
                            <p class="mt-2 text-sm text-muted">{{ $convite->nomeConvidante }} convidou você.</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                @if (! $contexto['invisivel'] && $contexto['vagas'] > 0)
                                    <form method="POST" action="{{ route('inicio.convites.aceitar', $convite->id) }}">@csrf<button class="min-h-12 w-full rounded-xl bg-coral text-sm font-bold">Aceitar</button></form>
                                @else
                                    <p class="self-center text-xs text-muted">{{ $contexto['invisivel'] ? 'Fique visível para aceitar.' : 'Limite de mesas atingido.' }}</p>
                                @endif
                                <form method="POST" action="{{ route('inicio.convites.recusar', $convite->id) }}">@csrf<button class="min-h-12 w-full rounded-xl bg-sand text-sm font-bold">Recusar</button></form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
        @if ($contexto['pedidosDeAmizade'] !== [])
            <section class="mt-6" aria-labelledby="pedidos-amizade-titulo">
                <h2 id="pedidos-amizade-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">De amizade</h2>
                <div class="mt-3 grid gap-4">
                    @foreach ($contexto['pedidosDeAmizade'] as $pedido)
                        <article wire:key="amizade-{{ $pedido['id'] }}" class="rounded-[22px] border border-coal/10 bg-white p-5">
                            <p class="text-xs font-black uppercase tracking-wide text-muted">Pedido de amizade</p>
                            <h3 class="mt-2 break-words text-xl font-black">{{ $pedido['nome'] }}</h3>
                            <p class="mt-2 text-sm text-muted">Quer ser seu amigo no Ranço.</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <form method="POST" action="{{ route('amigos.convites.aceitar', $pedido['id']) }}">@csrf<button class="min-h-12 w-full rounded-xl bg-coral text-sm font-bold">Aceitar</button></form>
                                <form method="POST" action="{{ route('amigos.convites.recusar', $pedido['id']) }}">@csrf<button class="min-h-12 w-full rounded-xl bg-sand text-sm font-bold">Recusar</button></form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @elseif ($area === 'jogar-juntos')
        @php
            $parts = $contexto['participacoes'];
            $idsAdmin = array_map(fn ($a) => $a['mesa']->id, $contexto['administradas']);
            $suaVez = array_values(array_filter($parts, fn ($p) => $p['status'] === 'Sua vez'));
            $emAndamento = array_values(array_filter($parts, fn ($p) => $p['status'] !== 'Sua vez' && ($p['emCurso'] || \Illuminate\Support\Str::startsWith($p['status'], 'Aguardando '))));
            $jaMostradas = array_merge(
                array_map(fn ($p) => $p['mesa']->id, $suaVez),
                array_map(fn ($p) => $p['mesa']->id, $emAndamento),
            );
            $minhas = array_values(array_filter($parts, fn ($p) => in_array($p['mesa']->id, $idsAdmin, true) && ! in_array($p['mesa']->id, $jaMostradas, true)));
            $participando = array_values(array_filter($parts, fn ($p) => ! in_array($p['mesa']->id, $idsAdmin, true) && ! in_array($p['mesa']->id, $jaMostradas, true)));
        @endphp
        <p class="mt-2 text-sm text-muted">Suas mesas, convites e partidas com a turma.</p>

        @if ($suaVez !== [])
            <section class="mt-6" aria-labelledby="juntos-suavez-titulo">
                <h2 id="juntos-suavez-titulo" class="text-[11px] font-black uppercase tracking-widest text-coral">Sua vez</h2>
                <div class="mt-3 grid gap-3">
                    @foreach ($suaVez as $p)
                        <article wire:key="suavez-{{ $p['mesa']->id }}" class="rounded-[22px] border border-coral/40 bg-white p-5">
                            @if ($p['jogo'])<p class="text-xs font-black uppercase tracking-wide text-muted">{{ $p['jogo'] }}</p>@endif
                            <h3 class="mt-1 break-words text-xl font-black tracking-tight">{{ $p['mesa']->nome }}</h3>
                            <a href="{{ $p['mesa']->acaoUrl ?? route('mesas.jogar', $p['mesa']->id) }}" wire:navigate class="mt-4 flex min-h-12 items-center justify-center rounded-xl bg-coral px-4 text-sm font-black text-coal">Jogar agora</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($emAndamento !== [])
            <section class="mt-6" aria-labelledby="juntos-andamento-titulo">
                <h2 id="juntos-andamento-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">Partidas em andamento</h2>
                <div class="mt-3 grid gap-4">
                    @foreach ($emAndamento as $p)
                        <x-mobile-mesa wire:key="andamento-{{ $p['mesa']->id }}" :contexto="$p" />
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mt-6" aria-labelledby="juntos-minhas-titulo">
            <div class="flex items-center justify-between gap-3">
                <h2 id="juntos-minhas-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">Minhas mesas</h2>
                <span class="shrink-0 text-xs font-bold text-muted" aria-label="{{ count($contexto['administradas']) }} mesas administradas abertas, limite {{ $contexto['limite'] }}">{{ count($contexto['administradas']) }} / {{ $contexto['limite'] }}</span>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                @if ($contexto['invisivel'])
                    <a href="{{ route('profile.edit') }}" wire:navigate class="inline-flex min-h-12 items-center rounded-xl bg-sand px-4 text-sm font-bold">Ficar visível</a>
                @elseif ($contexto['vagas'] > 0)
                    <a href="{{ route('mesas.formulario') }}" wire:navigate class="inline-flex min-h-12 items-center rounded-xl bg-coral px-4 text-sm font-black text-coal">+ Criar mesa</a>
                @else
                    <span class="text-sm font-bold">Limite de mesas atingido</span>
                @endif
            </div>
            @if ($contexto['vagas'] === 0 && ! $contexto['invisivel'])
                <p class="mt-3 text-xs text-muted">O limite conta só as mesas que você administra. Encerre uma delas para criar outra.</p>
            @endif
            <div class="mt-4 grid gap-4">
                @forelse ($minhas as $p)
                    <x-mobile-mesa wire:key="minha-{{ $p['mesa']->id }}" :contexto="$p" />
                @empty
                    <p class="text-sm text-muted">Nenhuma mesa administrada por aqui.</p>
                @endforelse
            </div>
        </section>

        @if ($participando !== [])
            <section class="mt-6" aria-labelledby="juntos-participando-titulo">
                <h2 id="juntos-participando-titulo" class="text-[11px] font-black uppercase tracking-widest text-muted">Estou participando</h2>
                <div class="mt-3 grid gap-4">
                    @foreach ($participando as $p)
                        <x-mobile-mesa wire:key="participando-{{ $p['mesa']->id }}" :contexto="$p" />
                    @endforeach
                </div>
            </section>
        @endif
    @else
        <div class="mt-6 divide-y divide-coal/10 rounded-[22px] border border-coal/10 bg-white px-5">
            @foreach ([['Amigos', 'amigos.index', 'users'], ['Medalhas e troféus', 'perfil.mostrar', 'trophy'], ['Histórico', 'historico', 'clock']] as [$rotulo, $rota, $icone])
                <a href="{{ route($rota) }}" wire:navigate class="flex min-h-16 items-center gap-3 py-3 text-sm font-bold">
                    <x-dynamic-component :component="'flux::icon.'.$icone" class="size-5 shrink-0 text-muted" />{{ $rotulo }}
                    <flux:icon.chevron-right class="ml-auto size-4 text-muted" />
                </a>
            @endforeach
            <a href="{{ route('notificacoes') }}" wire:navigate class="flex min-h-16 items-center gap-3 py-3 text-sm font-bold">
                <span class="relative shrink-0">
                    <svg class="size-5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    @if ($avisosNaoLidos > 0)
                        <span class="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-coral text-[.5rem] font-black text-coal" aria-label="{{ $avisosNaoLidos }} não lido{{ $avisosNaoLidos > 1 ? 's' : '' }}">
                            {{ $avisosNaoLidos > 9 ? '9+' : $avisosNaoLidos }}
                        </span>
                    @endif
                </span>
                Avisos
                @if ($avisosNaoLidos > 0)
                    <span class="ml-auto text-[.7rem] font-black text-coral">{{ $avisosNaoLidos }}</span>
                @endif
                @if ($avisosNaoLidos === 0)
                    <flux:icon.chevron-right class="ml-auto size-4 text-muted" />
                @endif
            </a>
            @foreach ([['Perfil e conta', 'profile.edit', 'user-circle'], ['Configurações', 'security.edit', 'cog-6-tooth']] as [$rotulo, $rota, $icone])
                <a href="{{ route($rota) }}" wire:navigate class="flex min-h-16 items-center gap-3 py-3 text-sm font-bold">
                    <x-dynamic-component :component="'flux::icon.'.$icone" class="size-5 shrink-0 text-muted" />{{ $rotulo }}
                    <flux:icon.chevron-right class="ml-auto size-4 text-muted" />
                </a>
            @endforeach
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="flex min-h-16 w-full items-center gap-3 py-3 text-left text-sm font-bold"><flux:icon.arrow-right-start-on-rectangle class="size-5 text-muted" />Sair</button></form>
        </div>
    @endif
</section>
