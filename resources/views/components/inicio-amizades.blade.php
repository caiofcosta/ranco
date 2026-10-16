{{--
    Segunda coluna do Início: convites de amizade recebidos e as amizades, online primeiro.
    Sem nada, uma provocação do Provocador (momento amizade.vazia); sem frase, só o fato.
--}}
@props(['amigos', 'pedidos' => [], 'provocacao' => null, 'invisivel' => false])

<section class="min-w-0" aria-labelledby="amizades-titulo" data-test="amizades-na-home">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <h2 id="amizades-titulo" class="flex items-center gap-3 text-2xl font-black tracking-[-0.04em]">
            <flux:icon.users class="size-7" />
            Amizades
        </h2>
        <x-link :href="route('amigos.index')" discreto class="text-sm" data-test="convidar-amigos">Convidar amigos</x-link>
    </div>

    @error('amizade')
        <p class="mt-4 text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
    @enderror

    @if ($pedidos !== [])
        <ul class="mt-5 grid gap-3" aria-label="Convites de amizade">
            @foreach ($pedidos as $item)
                <li class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-[22px] border border-coal/10 bg-white p-4" data-test="pedido-de-amizade">
                    <x-avatar :nome="$item['nome']" :foto="$item['foto'] ?? null" class="size-11 rounded-full bg-sand text-sm font-black text-coal" />
                    <p class="min-w-0 flex-1 basis-32 text-sm [overflow-wrap:anywhere]"><strong class="font-black">{{ $item['nome'] }}</strong> quer jogar com você</p>
                    <div class="flex w-full items-center gap-2">
                        @if ($invisivel)
                            <p class="flex-1 text-sm text-muted">Fique visível para aceitar.</p>
                        @else
                            <form method="POST" action="{{ route('amigos.convites.aceitar', $item['pedido']) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-coal text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="aceitar-amizade">Aceitar</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('amigos.convites.recusar', $item['pedido']) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-coal/5 text-sm font-black text-coal transition hover:bg-coal/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal" data-test="recusar-amizade">Recusar</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($amigos['pessoas'] !== [])
        <p class="mt-5 text-sm text-muted">{{ $amigos['online'] }} online · {{ $amigos['offline'] }} offline</p>
        <ul class="mt-3 grid gap-2" data-test="amigos-na-home">
            @foreach ($amigos['pessoas'] as $amigo)
                <li class="flex items-center gap-3 rounded-2xl border border-coal/10 bg-white px-4 py-3">
                    <x-avatar :nome="$amigo['nome']" :foto="$amigo['foto'] ?? null" class="size-10 rounded-full bg-sand text-xs font-black text-coal">
                        @if ($amigo['online'])
                            <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-white bg-coral"></span>
                        @endif
                    </x-avatar>
                    <a href="{{ route('perfil.amigo', $amigo['id']) }}" class="min-w-0 flex-1 truncate rounded font-bold text-coal decoration-coral decoration-2 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">{{ $amigo['nome'] }}</a>
                    @if ($amigo['visto'] !== '')
                        <span class="shrink-0 text-xs text-muted">{{ $amigo['visto'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @elseif ($pedidos === [])
        @if ($provocacao)
            <x-voz-do-ranco class="mt-5" rotulo="nenhuma amizade ainda" data-test="provocacao-sem-amizades">{{ $provocacao }}</x-voz-do-ranco>
        @endif
        <div class="mt-4 rounded-[22px] border border-dashed border-coal/20 px-5 py-6" data-test="amizades-vazio">
            @unless ($provocacao)
                <p class="font-black text-coal">Sua galera ainda não chegou.</p>
            @endunless
            <p @class(['text-sm text-coal', 'mt-2' => ! $provocacao])>Pra achar seu amigo, use o e-mail que ele cadastrou no RANÇO. Se ele já tiver conta aqui, o convite aparece pra ele.</p>
            <a href="{{ route('amigos.index') }}" class="mt-4 inline-flex h-11 items-center justify-center rounded-xl bg-coal px-5 text-sm font-black text-cream transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal max-sm:w-full" data-test="convidar-amigos-vazio">Convidar amigos</a>
        </div>
    @endif
</section>
